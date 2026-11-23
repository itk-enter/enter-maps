<?php

declare(strict_types=1);

namespace App\Form;

use App\Builder\EmbedCode;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The options of the embed code on the embed code page.
 *
 * The form uses GET and has no name, so the options are in the query string,
 * for example "?height=900".
 */
final class EmbedCodeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('height', IntegerType::class, [
            'label' => 'builder.embed.height',
            'help' => 'builder.embed.height_help',
            'help_translation_parameters' => ['%min%' => EmbedCode::MIN_HEIGHT, '%max%' => EmbedCode::MAX_HEIGHT],
            'attr' => ['min' => EmbedCode::MIN_HEIGHT, 'max' => EmbedCode::MAX_HEIGHT, 'class' => 'w-32'],
            'constraints' => [
                new Assert\NotNull(),
                new Assert\Range(min: EmbedCode::MIN_HEIGHT, max: EmbedCode::MAX_HEIGHT),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'csrf_protection' => false,
            'data' => ['height' => EmbedCode::HEIGHT],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
