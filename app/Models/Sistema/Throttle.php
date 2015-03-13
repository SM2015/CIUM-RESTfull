<?php
namespace App\Models\Sistema;

use Cartalyst\Sentry\Throttling\Eloquent\Throttle as ThrottleModel;

class Throttle extends ThrottleModel {
	protected $table = 'Throttle';
}