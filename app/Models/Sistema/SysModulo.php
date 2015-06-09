<?php namespace App\Models\Sistema;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SysModulo extends Model {

	protected $table = 'SysModulo';
   	const CREATED_AT = 'creadoAl';    
    const UPDATED_AT = 'modificadoAl';
    const DELETED_AT = 'borradoAl';
	
	use SoftDeletes;
    protected $dates = ['borradoAl'];
	
	public function Acciones()
    {
        return $this->hasMany('App\Models\Sistema\SysModuloAccion','idModulo')->orderBy('nombre', 'ASC');
    }
	public function Padres()
    {
        return $this->belongsTo('App\Models\Sistema\SysModulo','idPadre')->orderBy('nombre', 'ASC');
    }
	public function Hijos()
    {
        return $this->hasMany('App\Models\Sistema\SysModulo','idPadre')->orderBy('nombre', 'ASC');
    }
}
