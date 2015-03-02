<?php namespace App\Models\Catalogos;

use Illuminate\Database\Eloquent\Model;

class PlazoAccion extends Model {

	protected $table = 'PlazoAccion';
   	const CREATED_AT = 'creadoAl';    
    const UPDATED_AT = 'modificadoAl';
    const DELETED_AT = 'borradoAl';
	
	public function Hallazgos()
    {
        return $this->hasMany('App\Models\Transacciones\Hallazgo');
    }
}
