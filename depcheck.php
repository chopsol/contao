<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

use AppBundle\AppBundle;
use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration())
    ->ignoreUnknownClasses([
        AppBundle::class,
        'Imagick',
        'Gmagick',
        'SensitiveParameter',
        'Swift_Attachment',
        'Swift_EmbeddedFile',
        'Swift_Mailer',
        'Swift_Message',
    ])
    ->disableExtensionsAnalysis()
    ->disableReportingUnmatchedIgnores()

    // Ignore the Contao components.
    ->ignoreErrorsOnPackage('contao-components/ace', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('contao-components/altcha', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('contao-components/choices', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('contao-components/colorbox', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('contao-components/contao', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('contao-components/datepicker', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('contao-components/dropzone', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('contao-components/handorgel', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('contao-components/jquery', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('contao-components/jquery-ui', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('contao-components/mediabox', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('contao-components/mootools', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('contao-components/simplemodal', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('contao-components/swipe', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('contao-components/swiper', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('contao-components/tablesort', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('contao-components/tablesorter', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('contao-components/tinymce4', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('contao-components/tristen-tablesort', [ErrorType::UNUSED_DEPENDENCY])

    // The manager plugin is a dev dependency because it is only required in the
    // managed edition.
    ->ignoreErrorsOnPackage('contao/manager-plugin', [ErrorType::DEV_DEPENDENCY_IN_PROD])

    // This packages provides the trigger_deprecation() function.
    ->ignoreErrorsOnPackage('symfony/deprecation-contracts', [ErrorType::UNUSED_DEPENDENCY])

    // This package provides the "sanitize_html" Twig filter.
    ->ignoreErrorsOnPackage('symfony/html-sanitizer', [ErrorType::UNUSED_DEPENDENCY])

    // Monolog is a dev dependency because it is only set up in the managed edition.
    ->ignoreErrorsOnPackage('symfony/monolog-bundle', [ErrorType::DEV_DEPENDENCY_IN_PROD])

    // These packages provide global functions if the PHP extensions are missing.
    ->ignoreErrorsOnPackage('symfony/polyfill-intl-idn', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('symfony/polyfill-mbstring', [ErrorType::UNUSED_DEPENDENCY])

    // The rate limiter is required for the functional tests.
    ->ignoreErrorsOnPackage('symfony/rate-limiter', [ErrorType::UNUSED_DEPENDENCY])

    // The web profiler uses the stopwatch component if it is installed.
    ->ignoreErrorsOnPackage('symfony/stopwatch', [ErrorType::UNUSED_DEPENDENCY])

    // The web profiler is a dev dependency because it is only set up in the
    // managed edition.
    ->ignoreErrorsOnPackage('symfony/web-profiler-bundle', [ErrorType::DEV_DEPENDENCY_IN_PROD])

    // This package provides the "u" Twig filter which is e.g. used in the
    // template_skeleton.html.twig template.
    ->ignoreErrorsOnPackage('twig/string-extra', [ErrorType::UNUSED_DEPENDENCY])

    // We only use the assets from the web-auth/webauthn-stimulus package.
    ->ignoreErrorsOnPackage('web-auth/webauthn-stimulus', [ErrorType::UNUSED_DEPENDENCY])

    // These packages are required for the search integration.
    ->ignoreErrorsOnPackage('cmsig/seal-symfony-bundle', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('cmsig/seal-loupe-adapter', [ErrorType::UNUSED_DEPENDENCY])

    // This package is required by cmsig/seal-loupe-adapter and may therefore be a
    // shadow dependency.
    ->ignoreErrorsOnPackage('loupe/loupe', [ErrorType::SHADOW_DEPENDENCY])
;
