<?php namespace App\Models\Transacciones;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EvaluacionCalidad extends Model 
{
   	protected $table = 'EvaluacionCalidad';
   	const CREATED_AT = 'creadoAl';    
    const UPDATED_AT = 'modificadoAl';
    const DELETED_AT = 'borradoAl';

	use SoftDeletes;
    protected $dates = ['borradoAl'];
	
	public function criterios()
    {
        return $this->hasMany('App\Models\Transacciones\EvaluacionCalidadCriterio','idCriterio');
    }
	public function cone()
    {
		return $this->belongsTo('App\Models\Catalogos\ConeClues','clues','clues','clues');
    }
	public function clues()
    {
		return $this->belongsTo('App\Models\Catalogos\Clues','clues','clues','clues');
    }
	public function Usuarios()
    {
		return $this->belongsTo('App\Models\Sistema\Usuario','idUsuario');
    }
}

?>