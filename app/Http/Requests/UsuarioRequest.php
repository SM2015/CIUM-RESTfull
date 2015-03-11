<?php namespace App\Http\Requests;

use App\Http\Requests\Request;

class UsuarioRequest extends Request {

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
			'nombres' => 'required',
			'apellido-paterno' => 'required',
			'apellido-materno' => 'required',
						
			'username' => 'required',
			'rol' => 'required',
			'email' => 'required|email',
			'password'=>'required',
			'password_confirm' =>'required|same:password'
		];
	}

}
