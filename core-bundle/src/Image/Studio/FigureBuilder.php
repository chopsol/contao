<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Image\Studio;

use Contao\CoreBundle\Exception\InvalidResourceException;
use Contao\CoreBundle\File\Metadata;
use Contao\FilesModel;
use Contao\Image\ImageInterface;
use Contao\Image\PictureConfiguration;
use Contao\PageModel;
use Contao\Validator;
use Psr\Container\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Webmozart\PathUtil\Path;

/**
 * Use the FigureBuilder class to create Figure result objects. The class
 * has a fluent interface to configure the desired output. When you are ready,
 * call build() to get a Figure. If you need another instance with similar
 * settings, you can alter values and call build() again - it will not affect
 * your first instance.
 */
class FigureBuilder
{
    /**
     * @var ContainerInterface
     */
    private $locator;

    /**
     * @var string
     */
    private $projectDir;

    /**
     * @var string
     */
    private $uploadPath;

    /**
     * @var array<string>
     */
    private $validExtensions;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * The resource's absolute file path.
     *
     * @var string|null
     */
    private $filePath;

    /**
     * The resource's file model if applicable.
     *
     * @var FilesModel|null
     */
    private $filesModel;

    /**
     * User defined size configuration.
     *
     * @phpcsSuppress SlevomatCodingStandard.Classes.UnusedPrivateElements
     *
     * @var int|string|array|PictureConfiguration|null
     */
    private $sizeConfiguration;

    /**
     * User defined custom locale. This will overwrite the default if set.
     *
     * @var string|null
     */
    private $locale;

    /**
     * User defined metadata. This will overwrite the default if set.
     *
     * @var Metadata|null
     */
    private $metadata;

    /**
     * Determines if a metadata should never be present in the output.
     *
     * @var bool
     */
    private $disableMetadata;

    /**
     * User defined link attributes. These will add to or overwrite the default values.
     *
     * @var array<string, string|null>
     */
    private $additionalLinkAttributes = [];

    /**
     * User defined lightbox resource or url. This will overwrite the default if set.
     *
     * @var string|ImageInterface|null
     */
    private $lightboxResourceOrUrl;

    /**
     * User defined lightbox size configuration. This will overwrite the default if set.
     *
     * @var mixed|null
     */
    private $lightboxSizeConfiguration;

    /**
     * User defined lightbox group identifier. This will overwrite the default if set.
     *
     * @var string|null
     */
    private $lightboxGroupIdentifier;

    /**
     * Determines if a lightbox (or "fullsize") image should be created.
     *
     * @var bool
     */
    private $enableLightbox;

    /**
     * User defined template options.
     *
     * @phpcsSuppress SlevomatCodingStandard.Classes.UnusedPrivateElements
     *
     * @var array<string, mixed>
     */
    private $options = [];

    /**
     * @internal Use the Contao\Image\Studio\Studio factory to get an instance of this class
     */
    public function __construct(ContainerInterface $locator, string $projectDir, string $uploadPath, array $validExtensions)
    {
        $this->locator = $locator;
        $this->projectDir = $projectDir;
        $this->uploadPath = $uploadPath;
        $this->validExtensions = $validExtensions;

        $this->filesystem = new Filesystem();
    }

    /**
     * Sets the image resource from a FilesModel.
     */
    public function fromFilesModel(FilesModel $filesModel): self
    {
        if ('file' !== $filesModel->type) {
            throw new InvalidResourceException("DBAFS item '{$filesModel->path}' is not a file.");
        }

        $this->filePath = Path::makeAbsolute($filesModel->path, $this->projectDir);
        $this->filesModel = $filesModel;

        if (!$this->filesystem->exists($this->filePath)) {
            throw new InvalidResourceException("No resource could be located at path '{$this->filePath}'.");
        }

        return $this;
    }

    /**
     * Sets the image resource from a tl_files UUID.
     */
    public function fromUuid(string $uuid): self
    {
        $filesModel = $this->filesModelAdapter()->findByUuid($uuid);

        if (null === $filesModel) {
            throw new InvalidResourceException("DBAFS item with UUID '$uuid' could not be found.");
        }

        return $this->fromFilesModel($filesModel);
    }

    /**
     * Sets the image resource from a tl_files ID.
     */
    public function fromId(int $id): self
    {
        $filesModel = $this->filesModelAdapter()->findByPk($id);

        if (null === $filesModel) {
            throw new InvalidResourceException("DBAFS item with ID '$id' could not be found.");
        }

        return $this->fromFilesModel($filesModel);
    }

    /**
     * Sets the image resource from an absolute or relative path.
     *
     * @param bool $autoDetectDbafsPaths Set to false to skip searching for a FilesModel
     */
    public function fromPath(string $path, bool $autoDetectDbafsPaths = true): self
    {
        // Make sure path is absolute and in a canonical form
        $path = Path::isAbsolute($path) ? Path::canonicalize($path) : Path::makeAbsolute($path, $this->projectDir);

        // Only check for a FilesModel if the resource is inside the upload path
        if ($autoDetectDbafsPaths && Path::isBasePath(Path::join($this->projectDir, $this->uploadPath), $path)) {
            $filesModel = $this->filesModelAdapter()->findByPath($path);

            if (null !== $filesModel) {
                return $this->fromFilesModel($filesModel);
            }
        }

        $this->filePath = $path;
        $this->filesModel = null;

        if (!$this->filesystem->exists($this->filePath)) {
            throw new InvalidResourceException("No resource could be located at path '{$this->filePath}'.");
        }

        return $this;
    }

    /**
     * Sets the image resource from an ImageInterface.
     */
    public function fromImage(ImageInterface $image): self
    {
        return $this->fromPath($image->getPath());
    }

    /**
     * Sets the image resource by guessing the identifier type.
     *
     * @param int|string|FilesModel|ImageInterface $identifier Can be a FilesModel, an ImageInterface, a tl_files UUID/ID/path or a file system path
     */
    public function from($identifier): self
    {
        if ($identifier instanceof FilesModel) {
            return $this->fromFilesModel($identifier);
        }

        if ($identifier instanceof ImageInterface) {
            return $this->fromImage($identifier);
        }

        if ($this->validatorAdapter()->isUuid($identifier)) {
            return $this->fromUuid($identifier);
        }

        if (is_numeric($identifier)) {
            return $this->fromId($identifier);
        }

        return $this->fromPath($identifier);
    }

    /**
     * Sets a size configuration that will be applied to the resource.
     *
     * @param int|string|array|PictureConfiguration|null $size A picture size configuration or reference
     */
    public function setSize($size): self
    {
        $this->sizeConfiguration = $size;

        return $this;
    }

    /**
     * Sets custom metadata.
     *
     * By default or if the argument is set to null, metadata is trying to be
     * pulled from the FilesModel.
     */
    public function setMetadata(?Metadata $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Disables creating/using metadata in the output even if it is present.
     */
    public function disableMetadata(bool $disable = true): self
    {
        $this->disableMetadata = $disable;

        return $this;
    }

    /**
     * Sets a custom locale.
     *
     * By default or if the argument is set to null, the locale is determined
     * from the request context and/or system settings.
     */
    public function setLocale(?string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Adds a custom link attribute.
     *
     * Set the value to null to remove it. If you want to explicitly remove an
     * auto-generated value from the results, set the $forceRemove flag to true.
     */
    public function setLinkAttribute(string $attribute, ?string $value, $forceRemove = false): self
    {
        if (null !== $value || $forceRemove) {
            $this->additionalLinkAttributes[$attribute] = $value;
        } else {
            unset($this->additionalLinkAttributes[$attribute]);
        }

        return $this;
    }

    /**
     * Sets all custom link attributes as an associative array.
     *
     * This will overwrite previously set attributes. If you want to explicitly
     * remove an auto-generated value from the results, set the respective
     * attribute to null.
     */
    public function setLinkAttributes(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            if (!\is_string($key) || !\is_string($value)) {
                throw new \InvalidArgumentException('Link attributes must be an array of type <string, string>.');
            }
        }

        $this->additionalLinkAttributes = $attributes;

        return $this;
    }

    /**
     * Sets the link href attribute.
     *
     * Set the value to null to use the auto-generated default.
     */
    public function setLinkHref(?string $url): self
    {
        $this->setLinkAttribute('href', $url);

        return $this;
    }

    /**
     * Sets a custom lightbox resource (file path or ImageInterface) or URL.
     *
     * By default or if the argument is set to null, the image/target will be
     * automatically determined from the metadata or base resource. For this
     * setting to take effect, make sure you have enabled the creation of a
     * lightbox by calling enableLightbox().
     *
     * @param string|ImageInterface|null $resourceOrUrl
     */
    public function setLightboxResourceOrUrl($resourceOrUrl): self
    {
        $this->lightboxResourceOrUrl = $resourceOrUrl;

        return $this;
    }

    /**
     * Sets a size configuration that will be applied to the lightbox image.
     *
     * For this setting to take effect, make sure you have enabled the creation
     * of a lightbox by calling enableLightbox().
     *
     * @param int|string|array|PictureConfiguration $size A picture size configuration or reference
     */
    public function setLightboxSize($size): self
    {
        $this->lightboxSizeConfiguration = $size;

        return $this;
    }

    /**
     * Sets a custom lightbox group ID.
     *
     * By default or if the argument is set to null, the ID will be empty. For
     * this setting to take effect, make sure you have enabled the creation of
     * a lightbox by calling enableLightbox().
     */
    public function setLightboxGroupIdentifier(?string $identifier): self
    {
        $this->lightboxGroupIdentifier = $identifier;

        return $this;
    }

    /**
     * Enables the creation of a lightbox image (if possible) and/or
     * outputting the respective link attributes.
     *
     * This setting is disabled by default.
     */
    public function enableLightbox(bool $enable = true): self
    {
        $this->enableLightbox = $enable;

        return $this;
    }

    /**
     * Sets all template options as an associative array.
     */
    public function setOptions(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Creates a result object with the current settings.
     */
    public function build(): Figure
    {
        if (null === $this->filePath) {
            throw new \LogicException('You need to set a resource before building the result.');
        }

        // Freeze settings to allow reusing this builder object
        $settings = clone $this;

        $imageResult = $this->locator
            ->get(Studio::class)
            ->createImage($settings->filePath, $settings->sizeConfiguration)
        ;

        // Define the values via closure to make their evaluation lazy
        return new Figure(
            $imageResult,
            \Closure::bind(
                function (Figure $figure): ?Metadata {
                    return $this->onDefineMetadata();
                },
                $settings
            ),
            \Closure::bind(
                function (Figure $figure): array {
                    return $this->onDefineLinkAttributes($figure);
                },
                $settings
            ),
            \Closure::bind(
                function (Figure $figure): ?LightboxResult {
                    return $this->onDefineLightboxResult($figure);
                },
                $settings
            ),
            $settings->options
        );
    }

    /**
     * Defines metadata on demand.
     */
    private function onDefineMetadata(): ?Metadata
    {
        if ($this->disableMetadata) {
            return null;
        }

        if (null !== $this->metadata) {
            return $this->metadata;
        }

        if (null === $this->filesModel) {
            return null;
        }

        // Get fallback locale list or use without fallbacks if explicitly set
        $locales = null !== $this->locale ? [$this->locale] : $this->getFallbackLocaleList();
        $metadata = $this->filesModel->getMetadata(...$locales);

        if (null !== $metadata) {
            return $metadata;
        }

        // If no metadata can be obtained from the model, we create a
        // container from the default meta fields with empty values instead
        $metaFields = $this->filesModelAdapter()->getMetaFields();

        return new Metadata(array_combine($metaFields, array_fill(0, \count($metaFields), '')));
    }

    /**
     * Defines link attributes on demand.
     */
    private function onDefineLinkAttributes(Figure $result): array
    {
        $linkAttributes = [];

        // Open in a new window if lightbox was requested but is invalid (fullsize)
        if ($this->enableLightbox && !$result->hasLightbox()) {
            $linkAttributes['target'] = '_blank';
        }

        return array_merge($linkAttributes, $this->additionalLinkAttributes);
    }

    /**
     * Defines the lightbox result (if enabled) on demand.
     */
    private function onDefineLightboxResult(Figure $result): ?LightboxResult
    {
        if (!$this->enableLightbox) {
            return null;
        }

        $getMetadataUrl = static function () use ($result): ?string {
            if (!$result->hasMetadata()) {
                return null;
            }

            return $result->getMetadata()->getUrl() ?: null;
        };

        $getResourceOrUrl = function ($target): array {
            if ($target instanceof ImageInterface) {
                return [$target, null];
            }

            $validExtension = \in_array(Path::getExtension($target), $this->validExtensions, true);
            $externalUrl = 1 === preg_match('#^https?://#', $target);

            if (!$validExtension) {
                return [null, null];
            }

            if ($externalUrl) {
                return [null, $target];
            }

            $filePath = Path::isAbsolute($target) ?
                Path::canonicalize($target) :
                Path::makeAbsolute($target, $this->projectDir);

            if (!is_file($filePath)) {
                $filePath = null;
            }

            return [$filePath, null];
        };

        // Use explicitly set data (1), fall back to using metadata (2) or use the base resource (3) if empty
        $lightboxResourceOrUrl = $this->lightboxResourceOrUrl ?? $getMetadataUrl() ?? $this->filePath;

        [$filePathOrImage, $url] = $getResourceOrUrl($lightboxResourceOrUrl);

        if (null === $filePathOrImage && null === $url) {
            return null;
        }

        return $this->locator
            ->get(Studio::class)
            ->createLightboxImage($filePathOrImage, $url, $this->lightboxSizeConfiguration, $this->lightboxGroupIdentifier)
        ;
    }

    private function filesModelAdapter()
    {
        $framework = $this->locator->get('contao.framework');
        $framework->initialize();

        return $framework->getAdapter(FilesModel::class);
    }

    private function validatorAdapter()
    {
        $framework = $this->locator->get('contao.framework');
        $framework->initialize();

        return $framework->getAdapter(Validator::class);
    }

    /**
     * Returns a list of locales (if available) in the following order:
     *  1. language of current page,
     *  2. root page fallback language.
     */
    private function getFallbackLocaleList(): array
    {
        $page = $GLOBALS['objPage'] ?? null;

        if (!$page instanceof PageModel) {
            return [];
        }

        $locales = [
            str_replace('-', '_', $page->language),
            str_replace('-', '_', $page->rootFallbackLanguage),
        ];

        return array_unique(array_filter($locales));
    }
}
