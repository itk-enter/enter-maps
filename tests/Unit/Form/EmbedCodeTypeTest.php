<?php

declare(strict_types=1);

namespace App\Tests\Unit\Form;

use App\Builder\EmbedCode;
use App\Form\EmbedCodeType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

final class EmbedCodeTypeTest extends TestCase
{
    public function testHasTheDefaultHeightBeforeASubmit(): void
    {
        $form = $this->factory()->create(EmbedCodeType::class);

        $this->assertSame(EmbedCode::HEIGHT, $form->get('height')->getData());
        $this->assertSame('', $form->getName());
        $this->assertSame('GET', $form->getConfig()->getMethod());
    }

    public function testAcceptsAHeightInTheRange(): void
    {
        $form = $this->factory()->create(EmbedCodeType::class);

        $form->submit(['height' => '900']);

        $this->assertTrue($form->isValid());
        $this->assertSame(900, $form->get('height')->getData());
    }

    public function testRejectsAHeightOutsideTheRange(): void
    {
        foreach (['299', '3001', ''] as $height) {
            $form = $this->factory()->create(EmbedCodeType::class);

            $form->submit(['height' => $height]);

            $this->assertFalse($form->isValid(), $height);
        }
    }

    private function factory(): \Symfony\Component\Form\FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory();
    }
}
