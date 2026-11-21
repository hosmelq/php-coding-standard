<?php

declare(strict_types=1);

namespace HosmelQ\PhpCodingStandard\Fixer;

use function Safe\file_get_contents;

use Override;
use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Fixer\FixerInterface;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use RuntimeException;
use SplFileInfo;
use Symplify\EasyCodingStandard\Caching\ChangedFilesDetector;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\FixerRunner\Application\FixerFileProcessor;
use Symplify\EasyCodingStandard\Skipper\Skipper\Skipper;
use Throwable;

final class ConvergeFixer extends AbstractFixer
{
    private const int MAX_PASSES = 10;

    /**
     * @var null|list<FixerInterface>
     */
    private null|array $fixers = null;

    public function __construct(
        private readonly ECSConfig $ecsConfig,
        private readonly Skipper $skipper,
    ) {
        parent::__construct();
    }

    #[Override]
    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Re-run active ECS fixers on modified files until formatting converges in a single pass.',
            [new CodeSample("<?php\n\$value = 1;\n")],
        );
    }

    #[Override]
    public function getName(): string
    {
        return 'HosmelQ/converge';
    }

    #[Override]
    public function getPriority(): int
    {
        return PHP_INT_MIN;
    }

    /**
     * @param Tokens<Token> $tokens
     */
    #[Override]
    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->count() > 0;
    }

    /**
     * @param Tokens<Token> $tokens
     */
    #[Override]
    protected function applyFix(SplFileInfo $file, Tokens $tokens): void
    {
        $filePath = $file->getPathname();
        $currentCode = $tokens->generateCode();
        $originalCode = is_file($filePath) ? file_get_contents($filePath) : null;

        if ($originalCode === $currentCode) {
            return;
        }

        $fixers = $this->fixers();

        if ($fixers === []) {
            return;
        }

        $code = $currentCode;
        $seen = [hash('xxh128', $code) => true];

        if ($originalCode !== null) {
            $seen[hash('xxh128', $originalCode)] = true;
        }

        try {
            for ($pass = 0; $pass < self::MAX_PASSES; ++$pass) {
                $passTokens = new Tokens();

                $passTokens->setCode($code);
                $passTokens->clearChanged();

                foreach ($fixers as $fixer) {
                    if (
                        $this->skipper->shouldSkipElementAndFilePath($fixer, $filePath)
                        || ! $fixer->supports($file)
                        || ! $fixer->isCandidate($passTokens)
                    ) {
                        continue;
                    }

                    try {
                        $fixer->fix($file, $passTokens);
                    } catch (Throwable $throwable) {
                        throw new RuntimeException(
                            sprintf(
                                'Fixing of "%s" file by "%s" failed: %s',
                                $filePath,
                                $fixer::class,
                                $throwable->getMessage(),
                            ),
                            (int) $throwable->getCode(),
                            $throwable,
                        );
                    }

                    if (! $passTokens->isChanged()) {
                        continue;
                    }

                    $passTokens->clearEmptyTokens();
                    $passTokens->clearChanged();
                }

                $nextCode = $passTokens->generateCode();

                if ($nextCode === $code) {
                    break;
                }

                $hash = hash('xxh128', $nextCode);

                if (isset($seen[$hash])) {
                    break;
                }

                $seen[$hash] = true;
                $code = $nextCode;
            }

            Tokens::clearCache();

            if ($code !== $currentCode) {
                $tokens->setCode($code);
            }
        } catch (Throwable $throwable) {
            Tokens::clearCache();

            $this->invalidateCache($filePath);

            throw $throwable;
        }
    }

    /**
     * @return list<FixerInterface>
     */
    private function fixers(): array
    {
        if ($this->fixers !== null) {
            return $this->fixers;
        }

        try {
            $candidates = $this->ecsConfig->make(FixerFileProcessor::class)->getCheckers();
            $needsSort = false;
        } catch (Throwable) {
            $candidates = [];

            foreach ($this->ecsConfig->getCheckerClasses() as $checkerClass) {
                if (is_a($checkerClass, FixerInterface::class, true)) {
                    $candidates[] = $this->ecsConfig->make($checkerClass);
                }
            }

            $needsSort = true;
        }

        $fixers = [];

        foreach ($candidates as $fixer) {
            if (! $fixer instanceof self) {
                $fixers[] = $fixer;
            }
        }

        if ($needsSort) {
            usort(
                $fixers,
                static fn (FixerInterface $first, FixerInterface $second): int
                    => $second->getPriority() <=> $first->getPriority(),
            );
        }

        return $this->fixers = $fixers;
    }

    private function invalidateCache(string $filePath): void
    {
        if (! is_file($filePath)) {
            return;
        }

        try {
            $this->ecsConfig->make(ChangedFilesDetector::class)->invalidateFilePath($filePath);
        } catch (Throwable) {
        }
    }
}
