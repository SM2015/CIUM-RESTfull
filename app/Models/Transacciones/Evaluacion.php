<?php namespace App\Models\Transacciones;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Evaluacion extends Model 
{
   	protected $table = 'Evaluacion';
   	const CREATED_AT = 'creadoAl';    
    const UPDATED_AT = 'modificadoAl';
    const DELETED_AT = 'borradoAl';

	use SoftDeletes;
    protected $dates = ['borradoAl'];
	
	public function criterios()
    {
        return $this->hasMany('App\Models\Transacciones\EvaluacionCriterio','idCriterio');
    } 
}

?>