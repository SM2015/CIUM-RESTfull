<?php namespace App;

use Illuminate\Database\Eloquent\Model;

class Indicador extends Model 
{
   	protected $table = 'Indicador';
   	const CREATED_AT = 'creadoAl';    
    const UPDATED_AT = 'modificadoAl';
    const DELETED_AT = 'borradoAl';
    protected $primaryKey = 'idIndicador';

    public function Criterios()
    {
        return $this->hasMany('App\Criterio');
    }
}

?>