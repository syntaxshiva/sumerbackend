<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;

use App\Http\Controllers\Controller;
use App\Repository\RouteRepositoryInterface;
use App\Repository\RouteStopRepositoryInterface;
use App\Repository\StopRepositoryInterface;
use App\Repository\RouteStopDirectionRepositoryInterface;
use Exception;
use Facade\FlareClient\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Traits\TripUtils;
use App\Traits\UserUtils;
use Illuminate\Support\Facades\Log;


use function PHPUnit\Framework\throwException;

class RouteController extends Controller
{
    use TripUtils;
    use UserUtils;
    //
    private $routeRepository;
    private $stopRepository;
    private $routeStopRepository;
    private $routeStopDirectionRepository;
    public function __construct(
        RouteRepositoryInterface $routeRepository,
        StopRepositoryInterface $stopRepository,
        RouteStopRepositoryInterface $routeStopRepository,
        RouteStopDirectionRepositoryInterface $routeStopDirectionRepository)
    {
        $this->routeRepository = $routeRepository;
        $this->stopRepository = $stopRepository;
        $this->routeStopRepository = $routeStopRepository;
        $this->routeStopDirectionRepository = $routeStopDirectionRepository;
    }

    public function index()
    {
        $authUser = auth()->user();

        //check if school
        if($authUser->role_id == 2)
        {
            $routes = $this->routeRepository->allWhere(['*'], ['stops'], [['school_id', '=', $authUser->id]]);
            //add stops_count
            foreach ($routes as $route) {
                $route->stops_count = count($route->stops);
            }
            //get all routes
            return response()->json($routes, 200);
        }
        else
        {
            //get all routes
            return response()->json($this->routeRepository->allWithCount(['*'], ['stops']), 200);
        }
    }


    public function getRoute($route_id)
    {
        $route = $this->routeRepository->findById($route_id, ['*'], ['stops', 'routeStops.routeStopDirections']);
        //decode the overview_path for each route direction
        $directions = [];
        $distance = 0;
        foreach ($route->routeStops as $routeStop) {
            if(count($routeStop->routeStopDirections) > 0) {
                $routeDirections = [];
                foreach ($routeStop->routeStopDirections as $key => $route_stop_direction) {
                    $path = json_decode($route_stop_direction->overview_path);
                    $d = [
                        'summary' => $route_stop_direction->summary,
                        'current' => $route_stop_direction->current,
                        'index' => $route_stop_direction->index,
                        'overview_path' => $path
                    ];
                    //calculate the distance
                    foreach ($path as $index => $point) {
                        if ($index > 0) {
                            $distance += $this->distance($path[$index - 1]->lat, $path[$index - 1]->lng, $point->lat, $point->lng);
                        }
                    }
                    array_push($routeDirections, $d);
                }
                array_push($directions, $routeDirections);
            }
        }
        $route->directions = $directions;
        $route->distance = $distance;
        //return the route
        return response()->json($route, 200);
    }

    //
    public function createEdit(Request $request)
    {
        $niceNames = [
            'route' => 'Route Name',
            'route_type' => 'Route Type',
            'stops' => 'Stops',
            'chosen_routes' => 'Routes between Stops',
            'ordered_directions' => 'Ordered Directions',
            'stops.*.address' => 'Stop Address',
            'stops.*.lat' => 'Stop Latitude',
            'stops.*.lng' => 'Stop Longitude',
        ];
        //validate the request
        $this->validate($request, [
            'route' => 'required|string',
            'route_type' => 'integer|nullable',
            'stops' => 'required',
            'chosen_routes' => 'required',
            'ordered_directions' => 'required',
            'stops.*.address' => 'required|string',
            'stops.*.lat' => 'required|numeric',
            'stops.*.lng' => 'required|numeric',
        ], [], $niceNames);
        //check if the routes count less than the stops count by 1
        if (count($request->stops) - 1 != count($request->chosen_routes) || count($request->stops) - 1 != count($request->ordered_directions)) {
            return response()->json(['message' => 'The routes count is not matched with the stops count'], 400);
        }
        $update = false;
        $route_id = null;
        $authUser = $request->user();
        // if($request->filled('id'))
        // {
        //     //update
        //     $update = true;
        //     $route_id = $request->id;

        //     //check if the route belongs to the school
        //     if(!$this->canViewRoute($route_id, $authUser->id))
        //     {
        //         return response()->json(['message' => 'You are not authorized to edit this route'], 401);
        //     }
        // }
        DB::beginTransaction();
        try {
            $routeDetails = [
                'name' => $request->route,
                'is_morning' => $request->route_type == 1 ? 1 : 0, //1 - morning, 0 - afternoon
                'school_id' => $authUser->id,
            ];

            if($update)
            {
                $this->routeRepository->update($route_id, $routeDetails);
                $savedRoute = $this->routeRepository->findById($route_id, ['*'], ['stops', 'routeStops']);
                $routeStopsIds = $savedRoute->routeStops->pluck('id')->toArray();
                $this->routeStopRepository->deleteWhere([
                        ['route_id', '=', $route_id],
                    ]);
                //delete all directions for this route
                $this->routeStopDirectionRepository->deleteByWhereIn('route_stop_id', $routeStopsIds);
            }
            else
            {
                //create new route
                $savedRoute = $this->routeRepository->create($routeDetails);
            }
            $order = 1;
            for ($i=0; $i < count($request->stops); $i++) {
                $stop = $request->stops[$i];
                //add school_id
                $stop['school_id'] = $authUser->id;
                //create the stop
                if(!array_key_exists('id', $stop))
                {
                    $savedStop = $this->stopRepository->create($stop);
                    $savedStopId = $savedStop->id;
                }
                else
                {
                    $savedStopId = $stop['id'];
                }
                $routeStop = [
                    'stop_id' => $savedStopId,
                    'route_id' => $savedRoute->id,
                    'order' => $order,
                ];
                $savedRouteStop = $this->routeStopRepository->create($routeStop);
                if($i > 0)
                {
                    //loop through ordered directions and save them to the database
                    $directions = $request->ordered_directions[$i-1];
                    $chosen_route = $request->chosen_routes[$i-1];
                    for ($j=0; $j < count($directions); $j++) {
                        $direction = $directions[$j];
                        $directionDetails = [
                            'route_stop_id' => $savedRouteStop->id,
                            'summary' => $direction['summary'],
                            'index' => $direction['index'],
                            'overview_path' => json_encode($direction['overview_path']),
                            'current' => $chosen_route == $j ? 1 : 0,
                        ];
                        $this->routeStopDirectionRepository->create($directionDetails);
                        //return response()->json($directionDetails, 400);
                    }
                }
                $order = $order + 1;
            }
            //save
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e->getMessage() . ', '. $e->getFile() . ', '. $e->getLine()], 422);
        }

        return response()->json(['success' => ['route created successfully']]);
    }

    public function destroy($route_id)
    {
        //get auth user
        $user = auth()->user();
        //check if the route belongs to the school
        if(!$this->canViewRoute($route_id, $user->id))
        {
            return response()->json(['message' => 'You are not authorized to delete this route'], 401);
        }
        //check if any route stop is assigned to any student in his settings
        $routeStops = $this->routeStopRepository->allWhere(['*'], ['pickupStudents', 'dropOffStudents'], ['route_id' => $route_id]);
        foreach ($routeStops as $routeStop) {
            if(count($routeStop->pickupStudents) > 0 || count($routeStop->dropOffStudents) > 0)
            {
                return response()->json(['message' => 'You cannot delete this route because it is assigned to some students'], 400);
            }
        }
        //delete the route
        $this->routeRepository->deleteById($route_id);
    }
}
