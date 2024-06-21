<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Tests\Functional;

use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Utility\Arrays;
use Symfony\Component\Yaml\Yaml;

/**
 * @property NodeTypeManager $nodeTypeManager
 */
trait FakeNodeTypeManagerTrait
{
    /**
     * @template T of object
     * @param class-string<T> $className
     *
     * @return T
     */
    abstract protected function getObject(string $className): object;

    private function loadFakeNodeTypes(): void
    {
        $configuration = $this->getObject(ConfigurationManager::class)->getConfiguration('NodeTypes');

        $fileIterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/Features'));

        /** @var \SplFileInfo $fileInfo */
        foreach ($fileIterator as $fileInfo) {
            if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'yaml' || strpos($fileInfo->getBasename(), 'NodeTypes.') !== 0) {
                continue;
            }

            $configuration = Arrays::arrayMergeRecursiveOverrule(
                $configuration,
                Yaml::parseFile($fileInfo->getRealPath()) ?? []
            );
        }

        $this->nodeTypeManager->overrideNodeTypes($configuration);
    }
}
