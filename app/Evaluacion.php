<?php namespace App;

use Illuminate\Database\Eloquent\Model;

class Evaluacion extends Model 
{
   	protected $table = 'Evaluacion';
   	const CREATED_AT = 'creadoAl';    
    const UPDATED_AT = 'modificadoAl';
    const DELETED_AT = 'borradoAl';
    protected $primaryKey = 'idEvaluacion';

    public function criterios()
    {
        return $this->belongsToMany('App\Criterio','EvaluacionCriterio','idEvaluacion','idCriterio')->withPivot('idEvaluacionCriterio', 'idEvaluacion', 'idCriterio','valor','hallazgo','accion');
    } 
}

?>