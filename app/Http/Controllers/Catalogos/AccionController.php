<?php namespace App\Http\Controllers\Catalogos;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Request;
use Response;
use Input;
use DB; 
use App\Models\Catalogos\Accion;
use App\Http\Requests\AccionRequest;


class AccionController extends Controller {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		$datos = Request::all();
		
		if(array_key_exists('pagina',$datos))
		{
			$pagina=$datos['pagina'];
			if($pagina == 0)
			{
				$pagina = 1;
			}
			if(array_key_exists('buscar',$datos))
			{
				$columna = $datos['columna'];
				$valor   = $datos['valor'];
				$accion = Accion::where($columna, 'LIKE', '%'.$valor.'%')->skip($pagina-1)->take($datos->get('limite'))->get();
			}
			else
			{
				$accion = Accion::skip($pagina-1)->take($datos['limite'])->get();
			}
			$total=Accion::all();
		}
		else
		{
			$accion = Accion::all();
			$total=$accion;
		}

		if(!$accion)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$accion,"total"=>count($total)),200);
			
		}
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store(AccionRequest $request)
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
			return Response::json(array("status"=>201,"messages"=>"Creado","value"=>$accion),201);
        } 
		else 
		{
            DB::rollback();
			return Response::json(array("status"=>500,"messages"=>"Error interno del servidor"),500);
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
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$accion),200);
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
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$accion),200);
		} 
		else 
		{
			DB::rollback();
			return Response::json(array('status'=> 304,"messages"=>'No modificado'),304);
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
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$accion),200);
		} 
		else 
		{
			DB::rollback();
			return Response::json(array('status'=> 500,"messages"=>'Error interno del servidor'),500);
		}
	}

}
