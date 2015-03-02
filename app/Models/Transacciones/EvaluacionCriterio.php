<?php namespace App\Models\Transacciones;

use Illuminate\Database\Eloquent\Model;

class EvaluacionCriterio extends Model 
{
   	protected $table = 'Evaluacion';
   	const CREATED_AT = 'creadoAl';    
    const UPDATED_AT = 'modificadoAl';
    const DELETED_AT = 'borradoAl';

    public function criterios()
    {
        return $this->belongsToMany('App\Models\Catalogos\Criterio');
    } 
	public function Evaluaciones()
    {
        return $this->belongsToMany('App\Models\Catalogos\Evaluacion');
    } 
}

?>