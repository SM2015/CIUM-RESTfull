<?php namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel {

	/**
	 * Pila global HTTP middleware de la aplicación.
	 *
	 * @var array
	 */
	protected $middleware = [
		'Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode',
		'Illuminate\Cookie\Middleware\EncryptCookies',
		'Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse',
		'Illuminate\Session\Middleware\StartSession',
		'Illuminate\View\Middleware\ShareErrorsFromSession',		
		'Barryvdh\Cors\Middleware\HandleCors'
	];

	/**
	 * Middleware ruta de la aplicación.
	 *
	 * @var array
	 */
	protected $routeMiddleware = [
		'auth' => 'App\Http\Middleware\Authenticate',
		'auth.basic' => 'Illuminate\Auth\Middleware\AuthenticateWithBasicAuth',
		'guest' => 'App\Http\Middleware\RedirectIfAuthenticated',
		'tokenPermiso'  => 'App\Http\Middleware\tokenPermiso',
		'token'  => 'App\Http\Middleware\token',
		//'csrf' => 'App\Http\Middleware\VerifyCsrfToken',
		'oauth' => 'App\Http\Middleware\OAuth',
	];

}
