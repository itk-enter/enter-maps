<?php

declare(strict_types=1);

namespace App\Tests\Unit\Form;

use App\Form\ImportType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

final class ImportTypeTest extends TestCase
{
    public function testNeedsTheJson(): void
    {
        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory();

        $form = $factory->create(ImportType::class);
        $form->submit(['json' => '{"version": 1}']);
        $this->assertTrue($form->isValid());
        $this->assertSame('{"version": 1}', $form->get('json')->getData());

        $empty = $factory->create(ImportType::class);
        $empty->submit(['json' => '']);
        $this->assertFalse($empty->isValid());
    }
}
