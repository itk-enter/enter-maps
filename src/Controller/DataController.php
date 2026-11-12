<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enter\DataProxy;
use App\Enter\EnterUnavailableException;
use App\Enter\InvalidFilterException;
use App\Enter\UnknownDataSetException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Returns the features of a data set from Enter as GeoJSON.
 */
final class DataController extends AbstractController
{
    #[Route(
        path: '/data/{dataSet}.geojson',
        name: 'app_data',
        requirements: ['dataSet' => '[A-Za-z0-9_.:-]+'],
        methods: [Request::METHOD_GET],
        stateless: true,
    )]
    public function __invoke(
        DataProxy $proxy,
        string $dataSet,
        #[MapQueryParameter] ?string $filter = null,
        #[MapQueryParameter('filter-lang')] string $filterLang = 'cql2-json',
    ): JsonResponse {
        if ('cql2-json' !== $filterLang) {
            throw new BadRequestHttpException('The only filter language is "cql2-json".');
        }

        try {
            $features = $proxy->getFeatures($dataSet, $filter);
        } catch (UnknownDataSetException $exception) {
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        } catch (InvalidFilterException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        } catch (EnterUnavailableException $exception) {
            throw new ServiceUnavailableHttpException(60, 'Enter is not available.', $exception);
        }

        $response = new JsonResponse(
            ['type' => 'FeatureCollection', 'features' => $features],
            headers: ['Content-Type' => 'application/geo+json'],
        );
        $response->setEncodingOptions(JsonResponse::DEFAULT_ENCODING_OPTIONS | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        $response->setPublic();
        $response->setMaxAge(60);

        return $response;
    }
}
