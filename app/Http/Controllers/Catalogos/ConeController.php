<?php namespace App\Http\Controllers\Catalogos;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use Response;
use Input;
use App\Models\Catalogos\Cone;
use DB;

class ConeController extends Controller {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		$cone = Cone::all();

		if(!$cone)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'));
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$cone));
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
            $cone = new Cone;
            $cone->nombre = $datos->get('nombre');

            if ($cone->save()) 
                $success = true;
        } 
		catch (\Exception $e) 
		{
        }
        if ($success) 
		{
            DB::commit();
			return Response::json(array("status"=>201,"messages"=>"Creado","value"=>$cone));
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
		$cone = Cone::find($id);

		if(!$cone)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'));
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$cone));
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
			$cone = Cone::find($id);
			$cone->nombre = $datos->get('nombre');

            if ($cone->save()) 
                $success = true;
		} 
		catch (\Exception $e) 
		{
        }
        if ($success)
		{
			DB::commit();
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$cone));
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
			$cone = Cone::find($id);
			$cone->delete();
			$success=true;
		} 
		catch (\Exception $e) 
		{
        }
        if ($success)
		{
			DB::commit();
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$cone));
		} 
		else 
		{
			DB::rollback();
			return Response::json(array('status'=> 500,"messages"=>'Error interno del servidor'));
		}
	}

}
