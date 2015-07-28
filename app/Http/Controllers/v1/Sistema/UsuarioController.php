<?php namespace App\Http\Controllers\v1\Sistema;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Request;
use Response;
use Input;
use DB; 
use Sentry;
use App\Models\Sistema\usuario;
use App\Http\Requests\UsuarioRequest;

class UsuarioController extends Controller 
{
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
				$usuario = Usuario::where($columna, 'LIKE', '%'.$valor.'%')->skip($pagina-1)->take($datos->get('limite'))->get();
			}
			else
			{
				$usuario = Usuario::with("Throttles")->skip($pagina-1)->take($datos['limite'])->get();
			}
			$total=Usuario::all();
		}
		else
		{
			$usuario = Usuario::all();
			$total=$usuario;
		}

		if(!$usuario)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$usuario,"total"=>count($total)),200);
			
		}
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store(UsuarioRequest $request)
	{
		$datos = Input::all();
		$success = false;
		
        try 
		{
			$user=array(
				'username' => isset($datos['username']) ? $datos['username'] : explode("@",$datos['email'])[0],
				'nombres' => $datos['nombres'],
				'apellidoPaterno' => isset($datos['apellidoPaterno']) ? $datos['apellidoPaterno'] : '',
				'apellidoMaterno' => isset($datos['apellidoMaterno']) ? $datos['apellidoMaterno'] : '',
				'cargo' => isset($datos['cargo']) ? $datos['cargo'] : '',
				'telefono' => isset($datos['telefono']) ? $datos['telefono'] :'',
				'email' => $datos['email'],
				'password' => isset($datos['password']) ? $datos['password'] : explode("@",$datos['email'])[0],
				'activated' => 1,
				'permissions'=>isset($datos['permissions']) ? $datos['permissions'] : array(),
				'nivel' => $datos["nivel"]
			);
			

            $usuario = Sentry::createUser($user);

			$role_array = $datos['grupos'];
			if(count($role_array) > 0 && $role_array !== '')
			{
				foreach ($role_array as $rol) 
				{
					$user_group = Sentry::findGroupById($rol);
					$usuario->addGroup($user_group);
				}
			}
			if($datos["nivel"]!=1)
			{
				
				DB::table('UsuarioZona')->where('idUsuario', "$usuario->id")->delete();				
				DB::table('UsuarioJurisdiccion')->where('idUsuario', "$usuario->id")->delete();
				
				foreach($datos['usuariozona'] as $zona)
				{
					if($zona!="")
					{
						if($datos["nivel"]==3)
							DB::table('UsuarioZona')->insert(	array('idUsuario' => "$usuario->id", 'idZona' => $zona["id"]) );	
						if($datos["nivel"]==2)
							DB::table('UsuarioJurisdiccion')->insert(	array('idUsuario' => "$usuario->id", 'jurisdiccion' => $zona["id"]) );	
					}					
				}	
			}
			
            if ($usuario) 
                $success = true;
        } 
		
		catch (\Cartalyst\Sentry\Users\LoginRequiredException $e)
		{
			    return Response::json(array("status"=>400,"messages"=>"Username es requerido"),400);
		}
		catch (\Cartalyst\Sentry\Users\PasswordRequiredException $e)
		{
			    return Response::json(array("status"=>400,"messages"=>"Password es requerido"),400);
		}
		catch (\Cartalyst\Sentry\Users\UserExistsException $e)
		{
			return Response::json(array("status"=>403,"messages"=>"Este nombre de usuario ya existe"),403);
		}
		catch (\Cartalyst\Sentry\Groups\GroupNotFoundException $e)
		{
		    return Response::json(array("status"=>404,"messages"=>"El grupo asignado no existe"),404);
		}
        if ($success) 
		{
			return Response::json(array("status"=>201,"messages"=>"Creado","data"=>$usuario),201);
        } 
		else 
		{
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
		$usuario = Usuario::with("Grupos")->find($id);		
		
		if(!$usuario)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			$usuario["nivel"] = $usuario->nivel;
			if($usuario->nivel==2)
			{
				$usuario['usuariozona'] = DB::table('UsuarioJurisdiccion')		
				->select(array("jurisdiccion as id","jurisdiccion as nombre"))
				->where('idUsuario',$id)->get();
			}
			else if($usuario->nivel==3)
			{
				$usuario['usuariozona'] = DB::table('UsuarioZona AS u')
				->leftJoin('Zona AS c', 'c.id', '=', 'u.idZona')			
				->select('*')
				->where('idUsuario',$id)->get();
			}
			else
				$usuario['usuariozona']=array();
			
			
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$usuario),200);
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
		$datos = Input::all();
		$success = false;
        try 
		{
			if(isset($datos['baneo']))
			{
				$usuario = Sentry::findThrottlerByUserId($id);
				if($usuario->isBanned())
				{
					$usuario->unBan();
				}
				else
				{
					$usuario->ban();
				}
				return Response::json(array("status"=>200,"messages"=>"Ok","data"=>$usuario),200);
			}
			
			$usuario = Sentry::findUserById($id);
			
			$usuario->username = isset($datos['username']) ? $datos['username'] : explode("@",$datos['email'])[0];
			$usuario->nombres = $datos['nombres'];
			$usuario->apellidoPaterno = $datos['apellidoPaterno'];
			$usuario->apellidoMaterno = $datos['apellidoMaterno'];
			$usuario->cargo = $datos['cargo'];
			$usuario->telefono = $datos['telefono'];
			$usuario->email = $datos['email'];				
			$usuario->activated = 1;
			$usuario->nivel = $datos["nivel"];
			
			$user_permission = array();
			if(count($datos['permissions'])>0)
			{				
				$permTemp=isset($data["permissions"])?$data["permissions"]:array();
				$permissions=array();
				foreach((array)$permTemp as $p)
				{
					$permTemp[$p]=-1;
					$permissions[$p]=$permTemp[$p];
				}
				$user_permission = $permissions;
			}
			foreach ($usuario->permissions as $key => $value) 			
			{
				if(!array_key_exists($key, $user_permission))
				{
					$user_permission[$key] = 0;
				}
			}							
			$usuario->permissions = $user_permission;
			
			if(isset($datos['password']))
				if($datos['password'] != "")
					$usuario->password = $datos['password'];
			

            if ($usuario->save()) 
                $success = true;

			$role_array = $datos['grupos'];
			
			$grupos = $usuario->getGroups();
			if(count($grupos)>0)
			{
				foreach ($grupos as $grupo) 
				{
					if(array_search($grupo->id, $role_array) === FALSE)
					{
						$usuario->removeGroup($grupo);
					}
				}
			}
					
			if(count($role_array) > 0 && $role_array !== '')
			{
				foreach ($role_array as $rol) 
				{
					$user_group = Sentry::findGroupById($rol);
					$usuario->addGroup($user_group);
				}
			} 
			if($datos["nivel"]!=1)
			{
				DB::table('UsuarioZona')->where('idUsuario', "$usuario->id")->delete();
				DB::table('UsuarioJurisdiccion')->where('idUsuario', "$usuario->id")->delete();
				
				foreach($datos['usuariozona'] as $zona)
				{
					if($zona!="")
					{
						if($datos["nivel"]==3)
							DB::table('UsuarioZona')->insert(	array('idUsuario' => "$usuario->id", 'idZona' => $zona["id"]) );	
						if($datos["nivel"]==2)
							DB::table('UsuarioJurisdiccion')->insert(	array('idUsuario' => "$usuario->id", 'jurisdiccion' => $zona["id"]) );	
					}					
				}	
			}
        } 
		
		catch (\Cartalyst\Sentry\Users\LoginRequiredException $e)
		{
			    return Response::json(array("status"=>400,"messages"=>"Username es requerido"),400);
		}				
		catch (\Cartalyst\Sentry\Groups\GroupNotFoundException $e)
		{
		    return Response::json(array("status"=>404,"messages"=>"El grupo asignado no existe"),404);
		}
        if ($success) 
		{
			return Response::json(array("status"=>200,"messages"=>"Ok","data"=>$usuario),200);
        } 
		else 
		{
			return Response::json(array("status"=>500,"messages"=>"Error interno del servidor"),500);
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
			$usuario = Usuario::find($id);
			$grupos = $usuario->Grupos();
			if(count($grupos)>0)
			{
				foreach ($grupos as $grupo) 
				{
					$usuario->removeGroup($grupo);				
				}
			}
			$usuario->delete();
			
			$success=true;
		} 
		catch (\Exception $e) 
		{
			throw $e;
        }
        if ($success)
		{
			DB::commit();
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$usuario),200);
		} 
		else 
		{
			DB::rollback();
			return Response::json(array('status'=> 500,"messages"=>'Error interno del servidor'),500);
		}
	}
	
	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function UsuarioInfo()
	{  
        try 
		{
			$user = Sentry::getUser();
			$usuario = Usuario::find($user->id);
			if ($usuario)
			{
				return Response::json(array("status"=>200,"messages"=>"ok","data"=>$usuario),200);
			} 
			else 
			{
				return Response::json(array('status'=> 500,"messages"=>'Error interno del servidor'),500);
			}
		} 
		catch (\Exception $e) 
		{
			throw $e;
        }
	}
	
	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function UpdateInfo()
	{
		$datos = Input::all();
		$success = false;
        try 
		{
			$user = Sentry::getUser();
			$usuario = Sentry::findUserById($user->id);
			
			$usuario->username = $datos['username'];
			$usuario->nombres = $datos['nombres'];
			$usuario->apellidoPaterno = $datos['apellidoPaterno'];
			$usuario->apellidoMaterno = $datos['apellidoMaterno'];
			$usuario->cargo = $datos['cargo'];
			$usuario->telefono = $datos['telefono'];
			$usuario->email = $datos['email'];				
			$usuario->activated = 1;
			$usuario->avatar = $datos['avatar'];					
			

            if ($usuario->save()) 
                $success = true;			
        } 
		
		catch (\Cartalyst\Sentry\Users\LoginRequiredException $e)
		{
			    return Response::json(array("status"=>400,"messages"=>"Username es requerido"),400);
		}				
		
        if ($success) 
		{
			return Response::json(array("status"=>200,"messages"=>"Ok","data"=>$usuario),200);
        } 
		else 
		{
			return Response::json(array("status"=>500,"messages"=>"Error interno del servidor"),500);
        }
	}

}