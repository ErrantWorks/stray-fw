<?php
/**
 * @brief Bootstrap router.
 * @singleton
 * @author nekith@gmail.com
 */

final class strayRouting extends strayASingleton
{
  /**
   * Host in the request.
   * @var string
   */
  private $_host;

  /**
   * Resolve routing request URL.
   * @param string $url request URL
   * @param string $method request HTTP method
   * @return strayRoutingRequest internal routing request
   */
  public function Route($url, $method)
  {
    $components = parse_url($url);
    if (false === $components)
      throw new strayExceptionError('can\'t execute this request');
    $components['method'] = $method;
    if (false === isset($components['path']))
      $components['path'] = '/';
    $request = new strayRoutingRequest();
    $this->_ResolveRoutes($components, $request);
    return $request;
  }

  /**
   * Resolve routes.
   * @param array $components URL components
   * @param strayRoutingRequest $request request
   */
  private function _ResolveRoutes(array $components, strayRoutingRequest $request)
  {
    $installRoutes = strayConfigInstall::fGetInstance()->GetRoutes();
    if (true === isset($installRoutes['routes']))
    {
      // install routes
      foreach ($installRoutes['routes'] as $route)
      {
        if (false === isset($route['app']))
          throw new strayExceptionError('install routes : no app for route ' . var_export($route, true));
        if (true === isset($route['subdomain']))
        {
          if (0 === stripos($components['host'], $route['subdomain'] . '.'))
          {
            $request->app = $route['app'];
            break;
          }
        }
        elseif (true === isset($route['url']))
        {
          if (true === isset($components['path']) && 0 === stripos($components['path'], $route['url']))
          {
            $request->app = $route['app'];
            $components['path'] = substr($route['app'], strlen($components['path']));
            break;
          }
        }
        else
          throw new strayExceptionError('install routes : can\'t parse route ' . var_export($route, true));
      }
      if (null === $request->app)
      {
        if (false === isset($installRoutes['defaults']) || false === isset($installRoutes['defaults']['app']))
          throw new strayExceptionError('install routes : can\'t find default app');
        $request->app = $installRoutes['defaults']['app'];
      }
      // app routes
      $appRoutes = strayConfigApp::fGetInstance($request->app)->GetRoutes();
      if (isset($appRoutes['routes']) === false)
        throw new strayExceptionError('app routes : no routes');
      foreach ($appRoutes['routes'] as $route)
      {
        if (false === isset($route['url']))
          throw new strayExceptionError('app routes : route has no url ' . var_export($route, true));
        if (false === isset($route['view']))
          throw new strayExceptionError('app routes : route has no view ' . var_export($route, true));
        if (false === isset($route['method']) || $components['method'] == 'GET' || $route['method'] == $components['method'])
        {
          $matches = null;
          if (1 < strlen($route['url']))
            $route['url'] = rtrim($route['url'], '/');
          if (true === isset($components['path']) && 1 === preg_match('#^' . $route['url'] . '$#', $components['path'], $matches))
          {
            list($widget, $view) = explode('.', $route['view']);
            $request->widget = $widget;
            $request->view = $view;
            array_walk($matches, function($v, $k) use ($request) {
                if (false === is_numeric($k))
                  $request->params[$k] = $v;
              });
            break;
          }
        }
      }
    }
  }

  /**
   * Set host.
   * @param string $host host
   */
  public function SetHost($host)
  {
    $this->_host = $host;
  }

  /**
   * Get the host in the request.
   * @return string host
   */
  public function GetHost()
  {
    return $this->_host;
  }
}
