<?php namespace App\Models\Sistema;

use Cartalyst\Sentry\Groups\Eloquent\Group as Group;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Grupo extends Group 
{
	protected $table = 'Grupo';
	protected static $userGroupsPivot = 'UsuarioGrupo';

	const CREATED_AT = 'creadoAl';    
    const UPDATED_AT = 'modificadoAl';
    const DELETED_AT = 'borradoAl';
	
	use SoftDeletes;
    protected $dates = ['borradoAl'];
		
}