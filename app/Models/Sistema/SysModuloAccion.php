<?php namespace App\Models\Sistema;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class SysModuloAccion extends Model 
{

   	protected $table = 'SysModuloAccion';

   	const CREATED_AT = 'creadoAl';    
    const UPDATED_AT = 'modificadoAl';
    const DELETED_AT = 'borradoAl';

	use SoftDeletes;
    protected $dates = ['borradoAl'];
	
    
    public function Modulos()
	{
	    return $this->belongsTo('App\Models\Sistema\SysModulo','idModulo');
	} 	
}

?>