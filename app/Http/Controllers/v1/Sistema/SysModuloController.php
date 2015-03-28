<?php namespace App\Http\Controllers\v1\Sistema;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Request;
use Response;
use Input;
use DB; 
use App\Models\Sistema\SysModulo;
use App\Http\Requests\SysModuloRequest;


class SysModuloController extends Controller {

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
				$sysModulo = SysModulo::with("Padres")->where($columna, 'LIKE', '%'.$valor.'%')->skip($pagina-1)->take($datos->get('limite'))->orderBy('idPadre', 'ASC')->get();
			}
			else
			{
				$sysModulo = SysModulo::with("Padres")->skip($pagina-1)->take($datos['limite'])->orderBy('idPadre', 'ASC')->get();
			}
			$total=SysModulo::with("Padres")->get();
		}
		else
		{
			$sysModulo = SysModulo::with("Padres")->orderBy('idPadre', 'ASC')->get();
			$total=$sysModulo;
		}

		if(!$sysModulo)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$sysModulo,"total"=>count($total)),200);
			
		}
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store(SysModuloRequest $request)
	{
		$datos = Input::json();
		$success = false;
		
        DB::beginTransaction();
        try 
		{
            $sysModulo = new SysModulo;
            $sysModulo->nombre = $datos->get('nombre');
			$sysModulo->idPadre = $datos->get('idPadre');
			$sysModulo->url = $datos->get('url');
			$sysModulo->icon = $datos->get('icon');
			$sysModulo->controladorAngular = $datos->get('controladorAngular');
			$sysModulo->controladorLaravel = $datos->get('controladorLaravel');
			$sysModulo->vista = $datos->get('vista')?'1':'0';

            if ($sysModulo->save()) 
                $success = true;
        } 
		catch (\Exception $e) 
		{
			
        }
        if ($success) 
		{
            DB::commit();
			return Response::json(array("status"=>201,"messages"=>"Creado","data"=>$sysModulo),201);
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
		$sysModulo = SysModulo::with("Padres")->find($id);

		if(!$sysModulo)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$sysModulo),200);
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
			$sysModulo = SysModulo::find($id);
			$sysModulo->nombre = $datos->get('nombre');
			$sysModulo->idPadre = $datos->get('idPadre');
			$sysModulo->url = $datos->get('url');
			$sysModulo->icon = $datos->get('icon');
			$sysModulo->controladorAngular = $datos->get('controladorAngular');
			$sysModulo->controladorLaravel = $datos->get('controladorLaravel');
			$sysModulo->vista = $datos->get('vista');

            if ($sysModulo->save()) 
                $success = true;
		} 
		catch (\Exception $e) 
		{
        }
        if ($success)
		{
			DB::commit();
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$sysModulo),200);
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
			$sysModulo = SysModulo::find($id);
			$sysModulo->delete();
			$success=true;
		} 
		catch (\Exception $e) 
		{
        }
        if ($success)
		{
			DB::commit();
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$sysModulo),200);
		} 
		else 
		{
			DB::rollback();
			return Response::json(array('status'=> 500,"messages"=>'Error interno del servidor'),500);
		}
	}
	
	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function menu()
	{
		// falta checar session usuario para filtro de menus
		$sysModulo = SysModulo::with("Hijos")->orderBy('idPadre', 'ASC')->get();

		if(!$sysModulo)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$sysModulo),200);
		}
	}
	
	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function moduloAccion()
	{
		$Modulo = SysModulo::with("Hijos")->orderBy('idPadre', 'ASC')->get();
		$sysModulo = array();
		foreach($Modulo as $item)
		{
			$sys = SysModulo::with("Acciones")->find($item->id);
		
			foreach($item->hijos as $h)
			{
				$h["acciones"]=SysModulo::with("Acciones")->find($h->id)->acciones;
				$item["hijos"]=$h;
			}
			
			$item["acciones"] = $sys->acciones;
			$sysModulo[]=$item;
		}		
		
		if(!$sysModulo)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$sysModulo),200);
		}
	}

}
