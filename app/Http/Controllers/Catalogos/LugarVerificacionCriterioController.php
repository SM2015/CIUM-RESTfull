<?php namespace App\Http\Controllers\Catalogos;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use Response;
use Input;
use App\Models\Catalogos\LugarVerificacionCriterio;
use DB; 

class LugarVerificacionCriterioController extends Controller {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		$lugarVC = LugarVerificacionCriterio::all();

		if(!$lugarVC)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'));
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$lugarVC));
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
            $lugarVC = new LugarVerificacionCriterio;
            $lugarVC->nombre = $datos->get('nombre');

            if ($lugarVC->save()) 
                $success = true;
        } 
		catch (\Exception $e) 
		{
        }
        if ($success) 
		{
            DB::commit();
			return Response::json(array("status"=>201,"messages"=>"Creado","value"=>$lugarVC));
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
		$lugarVC = LugarVerificacionCriterio::find($id);

		if(!$lugarVC)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'));
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$lugarVC));
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
			$lugarVC = LugarVerificacionCriterio::find($id);
			$lugarVC->nombre = $datos->get('nombre');

            if ($lugarVC->save()) 
                $success = true;
		} 
		catch (\Exception $e) 
		{
        }
        if ($success)
		{
			DB::commit();
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$lugarVC));
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
			$lugarVC = LugarVerificacionCriterio::find($id);
			$lugarVC->delete();
			$success=true;
		} 
		catch (\Exception $e) 
		{
        }
        if ($success)
		{
			DB::commit();
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$lugarVC));
		} 
		else 
		{
			DB::rollback();
			return Response::json(array('status'=> 500,"messages"=>'Error interno del servidor'));
		}
	}

}
