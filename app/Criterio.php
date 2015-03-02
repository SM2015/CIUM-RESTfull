<?php namespace App;

use Illuminate\Database\Eloquent\Model;

class Criterio extends Model 
{

   	protected $table = 'Criterio';

   	const CREATED_AT = 'creadoAl';    
    const UPDATED_AT = 'modificadoAl';
    const DELETED_AT = 'borradoAl';
    protected $primaryKey = 'idCriterio';

    public function Indicadores()
    {
        return $this->belongsTo('App\Indicador');
    }
    public function Evaluaciones()
	{
	    return $this->belongsToMany('App\Evaluacion');
	} 
}

?>