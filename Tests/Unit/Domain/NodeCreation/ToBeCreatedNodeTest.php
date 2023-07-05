<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Tests\Unit\Domain\NodeCreation;

use Flowpack\NodeTemplates\Domain\NodeCreation\NodeConstraintException;
use Flowpack\NodeTemplates\Domain\NodeCreation\TransientNode;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\Utility\ObjectAccess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class ToBeCreatedNodeTest extends TestCase
{
    private const NODE_TYPE_FIXTURES = /** @lang yaml */ <<<'YAML'
    'A:Collection.Allowed':
      constraints:
        nodeTypes:
          '*': false
          'A:Content1': true

    'A:Collection.Disallowed':
      constraints:
        nodeTypes:
          '*': false

    'A:WithDisallowedCollectionAsChildNode':
      childNodes:
        collection:
          type: 'A:Collection.Disallowed'

    'A:WithContent1AllowedCollectionAsChildNode':
      childNodes:
        collection:
          type: 'A:Collection.Allowed'

    'A:WithContent1AllowedCollectionAsChildNodeViaOverride':
      childNodes:
        collection:
          type: 'A:Collection.Disallowed'
          constraints:
            nodeTypes:
              'A:Content1': true

    'A:Content1': {}

    'A:Content2': {}

    'A:Content3': {}
    YAML;

    /** @var array<string, array<mixed>> */
    private array $nodeTypesFixture;

    /** @var array<string, NodeType> */
    private array $nodeTypes;

    public function setUp(): void
    {
        parent::setUp();
        $this->nodeTypesFixture = Yaml::parse(self::NODE_TYPE_FIXTURES);
    }

    /** @test */
    public function fromRegularAllowedChildNode(): void
    {
        $parentNode = TransientNode::forRegular($this->getNodeType('A:Content1'));
        self::assertSame($this->getNodeType('A:Content1'), $parentNode->getNodeType());
        $parentNode->requireConstraintsImposedByAncestorsAreMet($this->getNodeType('A:Content2'));
    }

    /** @test */
    public function forTetheredChildNodeAllowedChildNode(): void
    {
        $grandParentNode = TransientNode::forRegular($this->getNodeType('A:WithContent1AllowedCollectionAsChildNode'));

        $parentNode = $grandParentNode->forTetheredChildNode(NodeName::fromString('collection'));
        self::assertSame($this->getNodeType('A:Collection.Allowed'), $parentNode->getNodeType());

        $parentNode->requireConstraintsImposedByAncestorsAreMet($this->getNodeType('A:Content1'));
    }

    /** @test */
    public function forTetheredChildNodeAllowedChildNodeBecauseConstraintOverride(): void
    {
        $grandParentNode = TransientNode::forRegular($this->getNodeType('A:WithContent1AllowedCollectionAsChildNodeViaOverride'));

        $parentNode = $grandParentNode->forTetheredChildNode(NodeName::fromString('collection'));
        self::assertSame($this->getNodeType('A:Collection.Disallowed'), $parentNode->getNodeType());

        $parentNode->requireConstraintsImposedByAncestorsAreMet($this->getNodeType('A:Content1'));
    }

    /** @test */
    public function forRegularChildNodeAllowedChildNode(): void
    {
        $grandParentNode = TransientNode::forRegular($this->getNodeType('A:Content1'));

        $parentNode = $grandParentNode->forRegularChildNode($this->getNodeType('A:Content2'));
        self::assertSame($this->getNodeType('A:Content2'), $parentNode->getNodeType());

        $parentNode->requireConstraintsImposedByAncestorsAreMet($this->getNodeType('A:Content3'));
    }

    /** @test */
    public function fromRegularDisallowedChildNode(): void
    {
        $this->expectException(NodeConstraintException::class);
        $this->expectExceptionMessage('Node type "A:Content1" is not allowed for child nodes of type A:Collection.Disallowed');

        $parentNode = TransientNode::forRegular($this->getNodeType('A:Collection.Disallowed'));
        self::assertSame($this->getNodeType('A:Collection.Disallowed'), $parentNode->getNodeType());

        $parentNode->requireConstraintsImposedByAncestorsAreMet($this->getNodeType('A:Content1'));
    }

    /** @test */
    public function forTetheredChildNodeDisallowedChildNode(): void
    {
        $this->expectException(NodeConstraintException::class);
        $this->expectExceptionMessage('Node type "A:Content1" is not allowed below tethered child nodes "collection" of nodes of type "A:WithDisallowedCollectionAsChildNode"');

        $grandParentNode = TransientNode::forRegular($this->getNodeType('A:WithDisallowedCollectionAsChildNode'));

        $parentNode = $grandParentNode->forTetheredChildNode(NodeName::fromString('collection'));
        self::assertSame($this->getNodeType('A:Collection.Disallowed'), $parentNode->getNodeType());

        $parentNode->requireConstraintsImposedByAncestorsAreMet($this->getNodeType('A:Content1'));
    }

    /** @test */
    public function forRegularChildNodeDisallowedChildNode(): void
    {
        $this->expectException(NodeConstraintException::class);
        $this->expectExceptionMessage('Node type "A:Content1" is not allowed for child nodes of type A:Collection.Disallowed');

        $grandParentNode = TransientNode::forRegular($this->getNodeType('A:Content2'));

        $parentNode = $grandParentNode->forRegularChildNode($this->getNodeType('A:Collection.Disallowed'));
        self::assertSame($this->getNodeType('A:Collection.Disallowed'), $parentNode->getNodeType());

        $parentNode->requireConstraintsImposedByAncestorsAreMet($this->getNodeType('A:Content1'));
    }

    /**
     * Return a nodetype built from the nodeTypesFixture
     */
    protected function getNodeType(string $nodeTypeName): ?NodeType
    {
        if (!isset($this->nodeTypesFixture[$nodeTypeName])) {
            return null;
        }

        if (isset($this->nodeTypes[$nodeTypeName])) {
            return $this->nodeTypes[$nodeTypeName];
        }

        $configuration = $this->nodeTypesFixture[$nodeTypeName];
        $declaredSuperTypes = [];
        if (isset($configuration['superTypes']) && is_array($configuration['superTypes'])) {
            foreach ($configuration['superTypes'] as $superTypeName => $enabled) {
                $declaredSuperTypes[$superTypeName] = $enabled === true ? $this->getNodeType($superTypeName) : null;
            }
        }

        $nodeType = new NodeType(
            $nodeTypeName,
            $declaredSuperTypes,
            $configuration
        );

        $fakeNodeTypeManager = $this->getMockBuilder(NodeTypeManager::class)->onlyMethods(['getNodeType'])->getMock();

        $fakeNodeTypeManager->expects(self::any())->method('getNodeType')->willReturnCallback(fn ($nodeType) => $this->getNodeType($nodeType));

        ObjectAccess::setProperty($nodeType, 'nodeTypeManager', $fakeNodeTypeManager, true);

        return $this->nodeTypes[$nodeTypeName] = $nodeType;
    }
}
