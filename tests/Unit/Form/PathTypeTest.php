<?php

declare(strict_types=1);

namespace App\Tests\Unit\Form;

use App\Form\PathType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Forms;

final class PathTypeTest extends TestCase
{
    public function testIsATextFieldWithItsOwnBlock(): void
    {
        $form = Forms::createFormFactory()->create(PathType::class);

        $form->submit('address.streetAddress');

        $this->assertSame('address.streetAddress', $form->getData());
        $this->assertSame(['form', 'text', 'path', '_path'], $form->createView()->vars['block_prefixes']);
        $this->assertSame(TextType::class, new PathType()->getParent());
    }
}
