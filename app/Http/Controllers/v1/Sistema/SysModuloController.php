<?php namespace App\Http\Controllers\v1\Sistema;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Request;
use Response;
use Input;
use DB; 
use Sentry;
use App\Models\Sistema\SysModulo;
use App\Models\Sistema\Grupo;
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
				$total=$sysModulo;
			}
			else
			{
				$sysModulo = SysModulo::with("Padres")->skip($pagina-1)->take($datos['limite'])->orderBy('idPadre', 'ASC')->get();
				$total=SysModulo::with("Padres")->get();
			}
			
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
		try 
		{
			$sysModulo=[];
			$principal=DB::table('SysModulo')
			->select("*")
			->where('idPadre',"0")
			->where('vista',"1")
			->get();
			
			$Modulo = SysModulo::with("Hijos")->orderBy('idPadre', 'ASC')->orderBy('nombre', 'ASC')->get();
			$user = Sentry::getUser();

			$user->getGroups();
			$permiso="";
			foreach($user->groups as $group)
			{
				 $grupo = Grupo::find($group->id);
				 foreach($grupo->permissions as $key => $valor)	
					$permiso.=",".$key;
			}	
			$i=0;
			foreach($Modulo as $item)
			{
				$tiene=0;$hijos=[]; $i++;
				foreach($item->hijos as $hijo)
				{
					if($hijo->controladorLaravel!="")
					{
						if(strpos($permiso, $hijo->controladorLaravel))
						{
							array_push($hijos, $hijo->toArray());
							$tiene++;
						}
					}					
				}
				if($tiene>0)
				{
					$sysModulo[$i] = $item->toArray();
					$sysModulo[$i]["hijos"] = $hijos;
				}
			}
			
			if(!$sysModulo&&!$principal)
			{
				return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
			} 
			else 
			{
				return Response::json(array("status"=>200,"messages"=>"ok","data"=>$sysModulo, "principal" => $principal),200);
			}
		}
		catch (\Exception $e) 
		{
        }
	}
	
	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function moduloAccion()
	{
		try 
		{			
			$Modulo = SysModulo::orderBy('idPadre', 'ASC')->orderBy('nombre', 'ASC')->get();
			$sysModulo = array();
			
			$user = Sentry::getUser();
			
			$user->getGroups();
			$permiso="";
			foreach($user->groups as $group)
			{
				 $grupo = Grupo::find($group->id);
				 foreach($grupo->permissions as $key => $valor)	
					$permiso.=",".$key;
			}	
			$i=0;
					
			foreach($Modulo as $item)
			{	
				$existe=0;
				foreach($item->hijos as $h)
				{
					$accion = []; $hijos = [];
					$acciones = SysModulo::with("Acciones")->find($h->id)->acciones;
					
					foreach($acciones as $ac)
					{									
						if(strpos($permiso, $h->controladorLaravel.".".$ac->recurso))
						{
							array_push($accion, $ac->toArray());
							$existe++;
						}										
					}					
					if(count($accion)>0)
						$h["acciones"]=$accion;
					else
						$h["acciones"]=$acciones;
					$item["hijos"]=$h;				
				}
				$acciones = SysModulo::with("Acciones")->find($item->id)->acciones;
				$accion = []; $hijos = []; 
				foreach($acciones as $ac)
				{				
					if(strpos($permiso, $item->controladorLaravel.".".$ac->recurso))
					{
						array_push($accion, $ac->toArray());
						$existe++;
					}
				}	
				if($existe)
				{
					$item["acciones"] = $accion;				
					$sysModulo[]=$item;	
				}				
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
		catch (\Exception $e) 
		{
        }
	}
	
	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function permiso()
	{
		try 
		{			
			$Modulo = SysModulo::orderBy('idPadre', 'ASC')->orderBy('nombre', 'ASC')->get();
			$sysModulo = array();						
					
			foreach($Modulo as $item)
			{	
				$existe=0;
				foreach($item->hijos as $h)
				{
					$accion = []; $hijos = [];
					$acciones = SysModulo::with("Acciones")->find($h->id)->acciones;
					
					foreach($acciones as $ac)
					{
						array_push($accion, $ac->toArray());
						$existe++;						
					}					
					if(count($accion)>0)
						$h["acciones"]=$accion;
					else
						$h["acciones"]=$acciones;
					$item["hijos"]=$h;				
				}
				$acciones = SysModulo::with("Acciones")->find($item->id)->acciones;
				$accion = []; $hijos = []; 
				foreach($acciones as $ac)
				{				
					array_push($accion, $ac->toArray());
					$existe++;
					
				}	
				if($existe)
				{
					$item["acciones"] = $accion;				
					$sysModulo[]=$item;	
				}				
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
		catch (\Exception $e) 
		{
        }
	}
	
	public function ordenKey()
	{	
		$array=Input::json()->all();
		ksort($array);
		return Response::json($array);
	}
}
