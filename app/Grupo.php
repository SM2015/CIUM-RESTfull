<?php namespace App;
use Cartalyst\Sentry\Groups\Eloquent\Group as Group;

class Grupo extends Group 
{
	protected $table = 'Grupo';
	protected static $userGroupsPivot = 'UsuarioGrupo';

	const CREATED_AT = 'creadoAl';    
    const UPDATED_AT = 'modificadoAl';
    const DELETED_AT = 'borradoAl';
    protected $primaryKey = 'idGrupo';
}