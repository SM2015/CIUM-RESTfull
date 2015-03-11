<?php namespace App\Http\Controllers\Sistema;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Request;
use Response;
use Input;
use DB; 
use App\Models\Sistema\SysModuloAccion;
use App\Http\Requests\SysModuloAccionRequest;


class SysModuloAccionController extends Controller {

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
				$sysModuloAccion = SysModuloAccion::with('Modulos')->where($columna, 'LIKE', '%'.$valor.'%')->skip($pagina-1)->take($datos->get('limite'))->get();
			}
			else
			{
				$sysModuloAccion = SysModuloAccion::with('Modulos')->skip($pagina-1)->take($datos['limite'])->get();
			}
			$total=SysModuloAccion::with('Modulos')->get();
		}
		else
		{
			$sysModuloAccion = SysModuloAccion::with('Modulos')->get();
			$total=$sysModuloAccion;
		}

		if(!$sysModuloAccion)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$sysModuloAccion,"total"=>count($total)),200);
			
		}
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store(SysModuloAccionRequest $request)
	{
		$datos = Input::json();
		$success = false;
		
        DB::beginTransaction();
        try 
		{
            $sysModuloAccion = new SysModuloAccion;
            $sysModuloAccion->nombre = $datos->get('nombre');
			$sysModuloAccion->idModulo = $datos->get('idModulo');
			$sysModuloAccion->etiqueta = $datos->get('etiqueta');
			$sysModuloAccion->recurso = $datos->get('recurso');
			$sysModuloAccion->metodo = $datos->get('metodo');

            if ($sysModuloAccion->save()) 
                $success = true;
        } 
		catch (\Exception $e) 
		{
			
        }
        if ($success) 
		{
            DB::commit();
			return Response::json(array("status"=>201,"messages"=>"Creado","value"=>$sysModuloAccion),201);
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
		$sysModuloAccion = SysModuloAccion::find($id);

		if(!$sysModuloAccion)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$sysModuloAccion),200);
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
			$sysModuloAccion = SysModuloAccion::find($id);
			$sysModuloAccion->nombre = $datos->get('nombre');
			$sysModuloAccion->idModulo = $datos->get('idModulo');
			$sysModuloAccion->etiqueta = $datos->get('etiqueta');
			$sysModuloAccion->recurso = $datos->get('recurso');
			$sysModuloAccion->metodo = $datos->get('metodo');

            if ($sysModuloAccion->save()) 
                $success = true;
		} 
		catch (\Exception $e) 
		{
        }
        if ($success)
		{
			DB::commit();
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$sysModuloAccion),200);
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
			$sysModuloAccion = SysModuloAccion::find($id);
			$sysModuloAccion->delete();
			$success=true;
		} 
		catch (\Exception $e) 
		{
        }
        if ($success)
		{
			DB::commit();
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$sysModuloAccion),200);
		} 
		else 
		{
			DB::rollback();
			return Response::json(array('status'=> 500,"messages"=>'Error interno del servidor'),500);
		}
	}

}
