<?php

namespace Bolt;

use Silex;
use Symfony\Component\HttpFoundation\Response;


/**
 * Wrapper around Twig's render() function. Handles the following responsibilities:
 *
 * - Calls twig's render
 * - Stores a page in cache, if needed
 * - Store template (partials) in cache, if needed
 * - Fetches pages or template (partials) from cache
 *
 * @author Bob den Otter, bob@twokings.nl
 *
 */
class Render
{

    /**
     * Set up the object.
     *
     * @param Silex\Application $app
     */
    public function __construct(Silex\Application $app)
    {
        $this->app = $app;
    }

    /**
     *
     * Render a template, possibly store it in cache. Or, if applicable, return the cached result
     *
     * @param $template
     * @param array $vars
     * @return mixed
     */
    public function render($template, $vars = array())
    {

        // Start the 'stopwatch' for the profiler.
        $this->app['stopwatch']->start('bolt.render', 'template');

        if ($html = $this->fetchCachedPage($template)) {

            // Do nothing.. The page is fetched from cache..

        } else {

            $html = $this->app['twig']->render($template, $vars);

            $this->cacheRenderedPage($template, $html);

        }

        // Stop the 'stopwatch' for the profiler.
        $this->app['stopwatch']->stop('bolt.render');


        return $html;

    }

    /**
     * Postprocess the rendered HTML: insert the snippets, and stuff.
     *
     * @param Response $response
     * @return string
     */
    public function postProcess(Response $response)
    {
        $html = $response->getContent();

        $html = $this->app['extensions']->processSnippetQueue($html);

        $this->cacheRequest($html);

        return $html;

    }


    /**
     * Retrieve a  page (or basically, any template) from cache
     *
     * @param string $template
     * @return mixed
     */
    public function fetchCachedPage($template)
    {
        $key = md5($template . $this->app['request']->getRequestUri());

        return $this->app['cache']->fetch($key);

    }

    /**
     * Retrieve a fully cached page from cache.
     *
     * @return mixed
     */
    public function fetchCachedRequest()
    {
        $key = md5($this->app['request']->getRequestUri());

        return $this->app['cache']->fetch($key);

    }

    /**
     * Store a page (or basically, any template) to cache.
     *
     * @param $template
     * @param $html
     */
    public function cacheRenderedPage($template, $html)
    {

        if ($this->app['end'] == "frontend" && $this->app['config']->get('general/caching/templates')) {

            // Store it part-wise, with the correct template name..
            $key = md5($template . $this->app['request']->getRequestUri());
            $this->app['cache']->save($key, $html, 300);

        }

    }

    /**
     * Store a fully rendered (and postprocessed) page to cache.
     *
     * @param $html
     */
    public function cacheRequest($html) {

        if ($this->app['end'] == "frontend" && $this->app['config']->get('general/caching/request')) {

            // This is where the magic happens.. We also store it with an empty 'template' name,
            // So we can later fetch it by its request..
            $key = md5($this->app['request']->getRequestUri());
            $this->app['cache']->save($key, $html, 300);

        }

    }

}