<?php namespace App\Http\Controllers\Catalogos;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use Response;
use Input;
use App\Models\Catalogos\Indicador;
use DB; use Event;

class IndicadorController extends Controller {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		$indicador = Indicador::all();

		if(!$indicador)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'));
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$indicador));
		}
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store()
	{
		$datos = Input::json();
		$success = false;
        DB::beginTransaction();
        try 
		{
            $indicador = new Indicador;
            $indicador->codigo = $datos->get('codigo');
			$indicador->descripcion = $datos->get('descripcion');

            if ($indicador->save()) 
                $success = true;
        } 
		catch (\Exception $e) 
		{
        }
        if ($success) 
		{
            DB::commit();
			return Response::json(array("status"=>201,"messages"=>"Creado","value"=>$indicador));
        } 
		else 
		{
            DB::rollback();
			return Response::json(array("status"=>500,"messages"=>"Error interno del servidor"));
        }
		
	}

	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($id)
	{
		$indicador = Indicador::find($id);

		if(!$indicador)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'));
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$indicador));
		}
	}


	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($id)
	{
		$datos = Input::json();
		$success = false;
        DB::beginTransaction();
        try 
		{
			$indicador = Indicador::find($id);
			$indicador->codigo = $datos->get('codigo');
			$indicador->descripcion = $datos->get('descripcion');

            if ($indicador->save()) 
                $success = true;
		} 
		catch (\Exception $e) 
		{
        }
        if ($success)
		{
			DB::commit();
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$indicador));
		} 
		else 
		{
			DB::rollback();
			return Response::json(array('status'=> 304,"messages"=>'No modificado'));
		}
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function destroy($id)
	{
		$success = false;
        DB::beginTransaction();
        try 
		{
			$indicador = Indicador::find($id);
			$indicador->delete();
			$success=true;
		} 
		catch (\Exception $e) 
		{
        }
        if ($success)
		{
			DB::commit();
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$indicador));
		} 
		else 
		{
			DB::rollback();
			return Response::json(array('status'=> 500,"messages"=>'Error interno del servidor'));
		}
	}

}
