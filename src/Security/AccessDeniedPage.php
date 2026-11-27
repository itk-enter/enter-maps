<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Symfony\Contracts\Translation\TranslatableInterface;
use Twig\Environment;

use function Symfony\Component\Translation\t;

/**
 * The page for a login that failed, and for a user who is not an employee.
 * It does not send the user to the login again, because the provider would
 * log the same user in again, and the browser would go round in a loop.
 */
final readonly class AccessDeniedPage implements AccessDeniedHandlerInterface
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    /**
     * The user is logged in, but is not in the group of employees.
     */
    public function handle(Request $request, AccessDeniedException $accessDeniedException): Response
    {
        return $this->render(t('builder.security.not_employee.title'), t('builder.security.not_employee.text'), Response::HTTP_FORBIDDEN);
    }

    /**
     * The template translates the title and the text. Give them with t(), so
     * that the extraction of the translations finds the keys.
     */
    public function render(TranslatableInterface $title, TranslatableInterface $text, int $status): Response
    {
        return new Response($this->twig->render('security/denied.html.twig', ['title' => $title, 'text' => $text]), $status);
    }
}
