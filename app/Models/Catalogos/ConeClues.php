<?php
namespace App\Models\Catalogos;

use Illuminate\Database\Eloquent\Model;

class ConeClues extends Model {
	protected $table = 'coneClues';
	
	public function cone()
    {
		return $this->belongsTo('App\Models\Catalogos\Cone','idCone');
    }
}