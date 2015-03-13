<?php namespace App\Models\Sistema;

use Cartalyst\Sentry\Users\Eloquent\User as UsuarioModel;
use Illuminate\Auth\Reminders\RemindableInterface;

use Illuminate\Database\Eloquent\SoftDeletes;

class Usuario extends UsuarioModel 
{

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'Usuario';
	protected static $userGroupsPivot = 'UsuarioGrupo';

    const CREATED_AT = 'creadoAl';    
    const UPDATED_AT = 'modificadoAl';
    const DELETED_AT = 'borradoAl';

	use SoftDeletes;
    protected $dates = ['borradoAl'];
	
	public function Grupos()
    {
        return $this->belongsToMany('App\Models\Sistema\Grupo','UsuarioGrupo','usuario_id','grupo_id');
    }
	public function Throttles()
    {
        return $this->hasOne('App\Models\Sistema\Throttle','user_id');
    }
	
	/**
	 * Get the unique identifier for the user.
	 *
	 * @return mixed
	 */
	public function getAuthIdentifier()
	{
		return $this->getKey();
	}

	/**
	 * Get the password for the user.
	 *
	 * @return string
	 */
	public function getAuthPassword()
	{
		return $this->password;
	}

	/**
	 * Get the e-mail address where password reminders are sent.
	 *
	 * @return string
	 */
	public function getReminderEmail()
	{
		return $this->email;
	}
	public function getRememberToken()
	{
		return $this->remember_token;
	}

	public function setRememberToken($value)
	{
		$this->remember_token = $value;
	}

	public function getRememberTokenName()
	{
		return 'remember_token';
	}
	
	public function nombreCompleto()
	{
    	return $this->nombres.' '.$this->apellidoPaterno.' '.$this->apellidoMaterno;
    }
}