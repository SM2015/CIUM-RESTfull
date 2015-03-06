<?php namespace App\Models\Catalogos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Indicador extends Model 
{
   	protected $table = 'Indicador';
   	const CREATED_AT = 'creadoAl';    
    const UPDATED_AT = 'modificadoAl';
    const DELETED_AT = 'borradoAl';

	use SoftDeletes;
    protected $dates = ['borradoAl'];
	
    public function Criterios()
    {
        return $this->hasMany('App\Models\Catalogos\Criterio');
    }
}

?>