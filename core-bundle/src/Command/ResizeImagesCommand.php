<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Command;

use Contao\CoreBundle\Image\ImageFactoryInterface;
use Contao\Image\DeferredImageInterface;
use Contao\Image\DeferredImageStorageInterface;
use Contao\Image\DeferredResizerInterface;
use Contao\Image\ResizerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Webmozart\PathUtil\Path;

/**
 * Resize deferred images that have not been processed yet.
 *
 * @internal
 */
class ResizeImagesCommand extends Command
{
    protected static $defaultName = 'contao:resize-images';

    /**
     * @var ImageFactoryInterface
     */
    private $imageFactory;

    /**
     * @var ?DeferredResizerInterface
     */
    private $resizer;

    /**
     * @var string
     */
    private $targetDir;

    /**
     * @var DeferredImageStorageInterface
     */
    private $storage;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var int
     */
    private $terminalWidth;

    /**
     * @var SymfonyStyle
     */
    private $io;

    /**
     * @var ConsoleSectionOutput
     */
    private $tableOutput;

    /**
     * @var Table
     */
    private $table;

    public function __construct(ImageFactoryInterface $imageFactory, ResizerInterface $resizer, string $targetDir, DeferredImageStorageInterface $storage, Filesystem $filesystem = null)
    {
        $this->imageFactory = $imageFactory;
        $this->resizer = $resizer instanceof DeferredResizerInterface ? $resizer : null;
        $this->targetDir = $targetDir;
        $this->storage = $storage;
        $this->filesystem = $filesystem ?? new Filesystem();
        $this->terminalWidth = (new Terminal())->getWidth();

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('time-limit', 'l', InputOption::VALUE_OPTIONAL, 'Time limit in seconds', '0')
            ->addOption('concurrent', 'c', InputOption::VALUE_OPTIONAL, 'Run multiple processes concurrently with a value larger than 1 or pause between resizes to limit CPU utilization with values lower than 1.0', '1')
            ->addOption('throttle', 't', InputOption::VALUE_OPTIONAL, '(Deprecated) Use the concurrent option instead', false)
            ->addOption('image', null, InputArgument::OPTIONAL, 'Image name to resize a single image')
            ->addOption('no-sub-process', null, InputOption::VALUE_NONE, 'Do not start a sub process per resize')
            ->setDescription('Resizes deferred images that have not been processed yet.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->resizer instanceof DeferredResizerInterface) {
            throw new \RuntimeException('Deferred resizer not available');
        }

        if (!$output instanceof ConsoleOutputInterface) {
            throw new \InvalidArgumentException(sprintf('Output must be an instance of "%s"', ConsoleOutputInterface::class));
        }

        $this->io = new SymfonyStyle($input, $output->section());

        if (null !== $image = $input->getOption('image')) {
            return $this->resizeImage($image);
        }

        $timeLimit = (float) $input->getOption('time-limit');
        $concurrent = (float) $input->getOption('concurrent');

        if (false !== $input->getOption('throttle')) {
            trigger_deprecation('contao/core-bundle', '4.9', 'Using the throttle option is deprecated and will no longer work in Contao 5.0. Use the concurrent option instead.', E_USER_DEPRECATED);
            $this->io->warning('Using the throttle option is deprecated, use the concurrent option instead.');

            $throttle = (float) $input->getOption('throttle');

            if ($throttle < 0.001 || $throttle > 1) {
                throw new InvalidArgumentException(sprintf('Throttle value "%s" is invalid.', $throttle));
            }

            $concurrent *= $throttle;
        }

        if ($timeLimit < 0) {
            throw new InvalidArgumentException(sprintf('Time-limit value "%s" is invalid.', $timeLimit));
        }

        if ($concurrent <= 0) {
            throw new InvalidArgumentException(sprintf('Concurrent value "%s" is invalid.', $concurrent));
        }

        $this->tableOutput = $output->section();

        $this->table = new Table($this->tableOutput);
        $this->table->setHeaders(['Process ID', 'Count', 'Image']);
        $this->table->setColumnWidth(2, $this->terminalWidth - 25);
        $this->table->setColumnMaxWidth(2, $this->terminalWidth - 25);

        return $this->resizeImages($timeLimit, $concurrent, $input->getOption('no-sub-process'));
    }

    private function resizeImage(string $path, bool $quiet = false): int
    {
        if ($this->filesystem->exists(Path::join($this->targetDir, $path))) {
            return 0;
        }

        try {
            $image = $this->imageFactory->create(Path::join($this->targetDir, $path));
            $resizer = $this->resizer;

            if ($image instanceof DeferredImageInterface) {
                $resizer->resizeDeferredImage($image, false);
            }
        } catch (\Throwable $exception) {
            if ($this->io->isVerbose()) {
                $this->getApplication()->renderThrowable($exception, $this->io);
            } else {
                $this->io->writeln($exception->getMessage());
            }

            return 1;
        }

        if (!$quiet) {
            $this->io->writeln('Image "'.$path.'" resized successfully');
        }

        return 0;
    }

    private function resizeImages(float $timeLimit, float $concurrent, bool $noSubProcess): int
    {
        if (!$noSubProcess && $this->supportsSubProcesses()) {
            return $this->executeConcurrent($timeLimit, $concurrent);
        }

        $this->io->warning(
            "Running this command without sub processes.\n"
            .'This can lead to memory leaks and eventually let the execution fail.'
        );

        $this->executeInCurrentProcess($timeLimit, $concurrent);

        return 0;
    }

    private function supportsSubProcesses(): bool
    {
        $phpFinder = new PhpExecutableFinder();

        if (false === ($phpPath = $phpFinder->find())) {
            return false;
        }

        $process = new Process(array_merge([$phpPath], $_SERVER['argv'], ['--help']));

        return 0 === $process->run();
    }

    private function executeInCurrentProcess(float $timeLimit, float $concurrent): void
    {
        $startTime = microtime(true);

        $count = 0;
        $lastDuration = 0;

        try {
            foreach ($this->storage->listPaths() as $path) {
                $sleep = $concurrent < 1 ? (1 - $concurrent) / $concurrent * $lastDuration : 0;

                if ($timeLimit && microtime(true) - $startTime + $sleep > $timeLimit) {
                    $this->io->writeln('Time limit of '.$timeLimit.' seconds reached.');

                    return;
                }

                usleep((int) ($sleep * 1000000));

                $this->table->setRows([[getmypid(), ++$count, $path]]);
                $this->tableOutput->clear();
                $this->table->render();

                $resizeStart = microtime(true);

                $this->resizeImage($path, true);

                $lastDuration = microtime(true) - $resizeStart;
            }
        } finally {
            $this->tableOutput->clear();
            $this->io->writeln('Resized '.$count.' images.');
        }
    }

    private function executeConcurrent(float $timeLimit, float $concurrent): int
    {
        $startTime = microtime(true);
        $processStartTime = 0;

        $phpFinder = new PhpExecutableFinder();
        $phpPath = $phpFinder->find();

        $failedCount = 0;
        $processes = array_fill(0, (int) $concurrent ?: 1, null);
        $paths = array_fill(0, \count($processes), '');
        $counts = array_fill(0, \count($processes), 0);

        foreach ($this->storage->listPaths() as $path) {
            while (true) {
                if ($timeLimit && microtime(true) - $startTime > $timeLimit) {
                    $this->io->writeln('Time limit of '.$timeLimit.' seconds reached.');
                    break 2;
                }

                foreach ($processes as $index => $process) {
                    if (null !== $process) {
                        if ($process->isRunning()) {
                            continue;
                        }

                        if (!$this->finishSubProcess($process, $paths[$index])) {
                            ++$failedCount;
                        }

                        if ($concurrent < 1) {
                            usleep((int) (
                                (1 - $concurrent)
                                / $concurrent
                                * (microtime(true) - $processStartTime)
                                * 1000000
                            ));
                        }
                    }

                    $process = new Process(array_merge([$phpPath], $_SERVER['argv'], ['--image='.$path]));
                    $process->setTimeout(null);
                    $process->start();

                    if ($concurrent < 1) {
                        $processStartTime = microtime(true);
                    }

                    $processes[$index] = $process;
                    $paths[$index] = $path;
                    ++$counts[$index];

                    $this->updateOutput($processes, $counts, $paths);

                    continue 3;
                }

                usleep(15000);
            }
        }

        foreach ($processes as $index => $process) {
            if (null === $process) {
                continue;
            }

            if (!$this->finishSubProcess($process, $paths[$index])) {
                ++$failedCount;
            }

            unset($processes[$index]);
            $this->updateOutput($processes, $counts, $paths);
        }

        $this->tableOutput->clear();

        $count = array_sum($counts);

        $this->io->writeln('Resized '.($count - $failedCount).' images successfully.');

        if ($failedCount > 0) {
            $this->io->writeln('Resizing of '.$failedCount.' images failed.');
        }

        if (0 !== $failedCount && $count - $failedCount <= 0) {
            $this->io->error('No image could be resized successfully.');

            return 1;
        }

        return 0;
    }

    private function updateOutput(array $processes, array $counts, array $paths): void
    {
        $rows = [];

        foreach ($processes as $index => $process) {
            $rows[] = [$process ? $process->getPid() : '', $counts[$index], $paths[$index]];
        }

        $this->table->setRows($rows);
        $this->tableOutput->clear();
        $this->table->render();
    }

    private function finishSubProcess(Process $process, string $path): bool
    {
        try {
            $process->wait();
        } catch (ProcessSignaledException $exception) {
            $this->io->writeln($path.' failed: '.$exception->getMessage());

            return false;
        }

        if ($process->isSuccessful()) {
            return true;
        }

        $this->io->writeln($path.' failed');

        return false;
    }
}
