<?php namespace App\Http\Controllers\Catalogos;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use Response;
use Input;
use App\Models\Catalogos\Accion;
use DB; 

class AccionController extends Controller {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		$accion = Accion::all();

		if(!$accion)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'));
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$accion));
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
            $accion = new Accion;
            $accion->nombre = $datos->get('nombre');
			$accion->tipo = $datos->get('tipo');

            if ($accion->save()) 
                $success = true;
        } 
		catch (\Exception $e) 
		{
        }
        if ($success) 
		{
            DB::commit();
			return Response::json(array("status"=>201,"messages"=>"Creado","value"=>$accion));
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
		$accion = Accion::find($id);

		if(!$accion)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'));
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$accion));
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
			$accion = Accion::find($id);
			$accion->nombre = $datos->get('nombre');
			$accion->tipo = $datos->get('tipo');

            if ($accion->save()) 
                $success = true;
		} 
		catch (\Exception $e) 
		{
        }
        if ($success)
		{
			DB::commit();
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$accion));
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
			$accion = Accion::find($id);
			$accion->delete();
			$success=true;
		} 
		catch (\Exception $e) 
		{
        }
        if ($success)
		{
			DB::commit();
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$accion));
		} 
		else 
		{
			DB::rollback();
			return Response::json(array('status'=> 500,"messages"=>'Error interno del servidor'));
		}
	}

}
