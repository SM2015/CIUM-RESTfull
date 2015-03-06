<?php namespace App\Http\Requests;

use App\Http\Requests\Request;

class CriterioRequest extends Request {

	/**
	 * Determine if the user is authorized to make this request.
	 *
	 * @return bool
	 */
	public function authorize()
	{
		return true;
	}

	/**
	 * Get the validation rules that apply to the request.
	 *
	 * @return array
	 */
	public function rules()
	{
		return [
			'nombre' => 'required|min:3|max:255',
			'idIndicador' => 'required',
			'idCone' => 'required',
			'idLugarVerificacionCriterio' => 'required'				
		];
	}

}
