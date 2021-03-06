<?php

namespace App\Http\Controllers;

use App\Models\Search;
use Twilio\Rest\Client;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Http\Resources\SearchResource;
use App\Http\Resources\PropertyResource;
use App\Http\Requests\StoreSearchRequest;
use App\Http\Requests\UpdateSearchRequest;

class SearchController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api')->except('index');
    }

 /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = $request->get('query') ?? '*';

        $properties = Property::search($query, function($meilisearch, $query, $options) use ($request) {
            if ($filters = $request->except('query', 'per_page')) {
                $options['filter'] = '';

                foreach ($filters as $field => $value) {
                    $value = explode(',', $value);

                    if (count($value) === 1)
                        $value[1] = '=';

                    $options['filter'] .= $field . $value[1] . $value[0] . ($field === array_key_last($filters) ? '' : ' AND ');
                }
            }

            return $meilisearch->search($query, $options);
        })->paginate($request->get('per_page', 10));

        return PropertyResource::collection($properties);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreSearchRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreSearchRequest $request)
    {
        $search = Auth::user()->searches()->create($request->all());

        return response()->json([
            'message' => 'Search added successfully!',
            'data' => new SearchResource($search)
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Search  $search
     * @return \Illuminate\Http\Response
     */
    public function show(Search $search)
    {
        if (Gate::denies('access-search', $search))
            return response()->json(['error', 'You are not authorized to show this search.'], 403);

        return new SearchResource($search);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateSearchRequest  $request
     * @param  \App\Models\Search  $search
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateSearchRequest $request, Search $search)
    {
        if (Gate::denies('access-search', $search))
            return response()->json(['error', 'You are not authorized to update this search.'], 403);

        $search->update($request->all());

        return response()->json([
            'message' => 'Search updated successfully!',
            'data' => new SearchResource($search)
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Search  $search
     * @return \Illuminate\Http\Response
     */
    public function destroy(Search $search)
    {
        if (Gate::denies('access-search', $search))
            return response()->json(['error', 'You are not authorized to delete this search.'], 403);

        $search->delete();

        return response('', 204);
    }
}
