<?php namespace App\Models\Catalogos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Criterio extends Model 
{

   	protected $table = 'Criterio';

   	const CREATED_AT = 'creadoAl';    
    const UPDATED_AT = 'modificadoAl';
    const DELETED_AT = 'borradoAl';

	use SoftDeletes;
    protected $dates = ['borradoAl'];
	
    public function Indicadores()
    {
        return $this->belongsTo('App\Models\Catalogos\Indicador');
    }
    public function Evaluaciones()
	{
	    return $this->belongsToMany('App\Models\Catalogos\Evaluacion');
	} 
	public function Cones()
    {
        return $this->belongsTo('App\Models\Catalogos\Cone');
    }
	public function LugarVerificacionCriterios()
    {
        return $this->belongsTo('App\Models\Catalogos\LugarVerificacionCriterio');
    }
}

?>