<?php namespace App\Models\Transacciones;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EvaluacionCriterio extends Model 
{
   	protected $table = 'EvaluacionCriterio';
   	const CREATED_AT = 'creadoAl';    
    const UPDATED_AT = 'modificadoAl';
    const DELETED_AT = 'borradoAl';

	use SoftDeletes;
    protected $dates = ['borradoAl'];
	
	public function Evaluaciones()
    {
        return $this->belongsTo('App\Models\Transacciones\Evaluacion','idCriterio');
    } 
}
?>