<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Tests\Functional\Features\Exceptions;

use Flowpack\NodeTemplates\Tests\Functional\AbstractNodeTemplateTestCase;
use Flowpack\NodeTemplates\Tests\Functional\WithConfigurationTrait;

class ExceptionsTest extends AbstractNodeTemplateTestCase
{
    use WithConfigurationTrait;

    /** @test */
    public function exceptionsAreCaughtAndPartialTemplateIsBuild(): void
    {
        $createdNode = $this->createNodeInto(
            $this->homePageMainContentCollectionNode,
            'Flowpack.NodeTemplates:Content.SomeExceptions',
            []
        );

        $this->assertLastCreatedTemplateMatchesSnapshot('SomeExceptions');

        $this->assertCaughtExceptionsMatchesSnapshot('SomeExceptions');
        $this->assertNodeDumpAndTemplateDumpMatchSnapshot('SomeExceptions', $createdNode);
    }

    /** @test */
    public function exceptionsAreCaughtAndPartialTemplateIsNotBuild(): void
    {
        $this->withMockedConfigurationSettings([
            'Flowpack' => [
                'NodeTemplates' => [
                    'errorHandling' => [
                        'templateConfigurationProcessing' => [
                            'stopOnException' => true
                        ]
                    ]
                ]
            ]
        ], function () {
            $createdNode = $this->createNodeInto(
                $this->homePageMainContentCollectionNode,
                'Flowpack.NodeTemplates:Content.OnlyExceptions',
                []
            );

            $this->assertLastCreatedTemplateMatchesSnapshot('OnlyExceptions');

            $this->assertCaughtExceptionsMatchesSnapshot('OnlyExceptions');
            $this->assertNodeDumpAndTemplateDumpMatchSnapshot('OnlyExceptions', $createdNode);
        });
    }

}
