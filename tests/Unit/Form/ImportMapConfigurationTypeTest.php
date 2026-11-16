<?php

declare(strict_types=1);

namespace App\Tests\Unit\Form;

use App\Form\ImportMapConfigurationType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validation;

final class ImportMapConfigurationTypeTest extends TestCase
{
    private FormFactoryInterface $factory;

    protected function setUp(): void
    {
        $this->factory = Forms::createFormFactoryBuilder()
            ->addExtension(new HttpFoundationExtension())
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory();
    }

    public function testAcceptsAJsonFile(): void
    {
        $form = $this->factory->create(ImportMapConfigurationType::class);

        $form->submit(['file' => $this->upload('toilets.json', \dirname(__DIR__, 2).'/Fixtures/map-config/toilets.json')]);

        $this->assertTrue($form->isValid(), (string) $form->getErrors(true));
    }

    public function testRejectsAMissingFile(): void
    {
        $form = $this->factory->create(ImportMapConfigurationType::class);

        $form->submit(['file' => null]);

        $this->assertSame(['builder.import.no_file'], $this->errors($form));
    }

    public function testRejectsAFileThatIsNotJson(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'map');
        file_put_contents($path, 'Not JSON');
        $form = $this->factory->create(ImportMapConfigurationType::class);

        $form->submit(['file' => $this->upload('map.txt', $path)]);

        $this->assertSame(['builder.import.not_json'], $this->errors($form));
        unlink($path);
    }

    private function upload(string $name, string $path): UploadedFile
    {
        return new UploadedFile($path, $name, test: true);
    }

    /**
     * @param \Symfony\Component\Form\FormInterface<mixed> $form
     *
     * @return list<string>
     */
    private function errors(\Symfony\Component\Form\FormInterface $form): array
    {
        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessageTemplate();
        }

        return $errors;
    }
}
