<?php
class Router {
    private $routes = [];

    public function add($method, $path, $handler) {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler
        ];
    }

    public function dispatch() {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = isset($_GET['r']) ? $_GET['r'] : 'home';
        
        // Normaliza a rota
        $uri = trim($uri, '/');
        if ($uri === '') $uri = 'home';

        foreach ($this->routes as $route) {
            if ($route['path'] === $uri && $route['method'] === $method) {
                $this->handle($route['handler']);
                return true;
            }
        }

        // Fallback para GET se não encontrar (simplificação)
        foreach ($this->routes as $route) {
            if ($route['path'] === $uri) {
                $this->handle($route['handler']);
                return true;
            }
        }

        // 404
        return false;
    }

    private function handle($handler) {
        if (is_callable($handler)) {
            call_user_func($handler);
        } elseif (is_array($handler)) {
            $controllerName = $handler[0];
            $methodName = $handler[1];
            
            require_once __DIR__ . '/../Controllers/' . $controllerName . '.php';
            
            // Try global namespace first
            if (class_exists($controllerName)) {
                $controller = new $controllerName();
            } 
            // Try App\Controllers namespace
            elseif (class_exists('App\\Controllers\\' . $controllerName)) {
                $className = 'App\\Controllers\\' . $controllerName;
                $controller = new $className();
            } else {
                die("Controller class $controllerName not found.");
            }

            if (method_exists($controller, $methodName)) {
                $controller->$methodName();
            } else {
                die("Method $methodName not found in controller $controllerName.");
            }
        }
    }
}
