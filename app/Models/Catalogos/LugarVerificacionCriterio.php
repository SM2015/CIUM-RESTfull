<?php namespace App\Models\Catalogos;

use Illuminate\Database\Eloquent\Model;

class LugarVerificacionCriterio extends Model 
{

   	protected $table = 'LugarVerificacionCriterio';

   	const CREATED_AT = 'creadoAl';    
    const UPDATED_AT = 'modificadoAl';
    const DELETED_AT = 'borradoAl';

    public function Criterios()
    {
        return $this->hasMany('App\Models\Catalogos\Criterio');
    }
}

?>