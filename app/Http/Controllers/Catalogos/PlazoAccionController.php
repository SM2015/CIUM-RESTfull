<?php namespace App\Http\Controllers\Catalogos;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use Response;
use Input;
use App\Models\Catalogos\PlazoAccion;
use DB; 

class PlazoAccionController extends Controller {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		$plazoAccion = PlazoAccion::all();

		if(!$plazoAccion)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'));
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$plazoAccion));
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
            $plazoAccion = new PlazoAccion;
            $plazoAccion->nombre = $datos->get('nombre');

            if ($plazoAccion->save()) 
                $success = true;
        } 
		catch (\Exception $e) 
		{
        }
        if ($success) 
		{
            DB::commit();
			return Response::json(array("status"=>201,"messages"=>"Creado","value"=>$plazoAccion));
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
		$plazoAccion = PlazoAccion::find($id);

		if(!$plazoAccion)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'));
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$plazoAccion));
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
			$plazoAccion = PlazoAccion::find($id);
			$plazoAccion->nombre = $datos->get('nombre');

            if ($plazoAccion->save()) 
                $success = true;
		} 
		catch (\Exception $e) 
		{
        }
        if ($success)
		{
			DB::commit();
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$plazoAccion));
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
			$plazoAccion = PlazoAccion::find($id);
			$plazoAccion->delete();
			$success=true;
		} 
		catch (\Exception $e) 
		{
        }
        if ($success)
		{
			DB::commit();
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$plazoAccion));
		} 
		else 
		{
			DB::rollback();
			return Response::json(array('status'=> 500,"messages"=>'Error interno del servidor'));
		}
	}

}
