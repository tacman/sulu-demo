<?php

declare(strict_types=1);

namespace App\Controller\Website;

use Sulu\Route\Application\Routing\Matcher\RouteCollectionForRequestLoaderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class SuluRouteController extends AbstractController
{
    /**
     * @param iterable<RouteCollectionForRequestLoaderInterface> $routeLoaders
     */
    public function __construct(
        #[AutowireIterator('sulu_route.route_collection_for_request_loader')]
        private readonly iterable $routeLoaders,
    ) {
    }

    #[Route('/{path}', name: 'app.sulu_website_route', requirements: ['path' => '.*'], priority: -255)]
    public function __invoke(Request $request): Response
    {
        foreach ($this->routeLoaders as $routeLoader) {
            $routeCollection = $routeLoader->getRouteCollectionForRequest($request);

            foreach ($routeCollection as $route) {
                $defaults = $route->getDefaults();
                $controller = $defaults['_controller'] ?? null;

                if (!\is_string($controller)) {
                    continue;
                }

                unset($defaults['_controller']);
                $request->attributes->add($defaults);

                return $this->forward($controller, $defaults, $request->query->all());
            }
        }

        throw new NotFoundHttpException(\sprintf('No Sulu route found for "%s".', $request->getPathInfo()));
    }
}
