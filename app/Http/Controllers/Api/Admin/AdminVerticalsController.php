<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Flight;
use App\Models\Car;
use App\Models\Experience;
use App\Models\Transfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminVerticalsController extends Controller
{
    // ==========================================
    // FLIGHTS CRUD
    // ==========================================

    public function indexFlights(Request $request)
    {
        $query = Flight::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('airline', 'like', "%{$search}%")
                  ->orWhere('flight_number', 'like', "%{$search}%")
                  ->orWhere('departure_airport_code', 'like', "%{$search}%")
                  ->orWhere('arrival_airport_code', 'like', "%{$search}%");
            });
        }

        $flights = $query->orderBy('id', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $flights
        ]);
    }

    public function storeFlight(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'airline' => 'required|string|max:255',
            'flight_number' => 'required|string|max:100',
            'departure_airport_code' => 'required|string|size:3',
            'departure_airport_name' => 'required|string|max:255',
            'arrival_airport_code' => 'required|string|size:3',
            'arrival_airport_name' => 'required|string|max:255',
            'departure_time' => 'required|date_format:H:i:s',
            'arrival_time' => 'required|date_format:H:i:s',
            'stops' => 'integer|min:0',
            'class' => 'string|max:50',
            'base_fare' => 'required|numeric|min:0',
            'taxes' => 'numeric|min:0',
            'currency' => 'string|size:3',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 400);
        }

        $flight = Flight::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Flight created successfully',
            'data' => $flight
        ], 201);
    }

    public function updateFlight(Request $request, $id)
    {
        $flight = Flight::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'airline' => 'sometimes|required|string|max:255',
            'flight_number' => 'sometimes|required|string|max:100',
            'departure_airport_code' => 'sometimes|required|string|size:3',
            'departure_airport_name' => 'sometimes|required|string|max:255',
            'arrival_airport_code' => 'sometimes|required|string|size:3',
            'arrival_airport_name' => 'sometimes|required|string|max:255',
            'departure_time' => 'sometimes|required|date_format:H:i:s',
            'arrival_time' => 'sometimes|required|date_format:H:i:s',
            'stops' => 'integer|min:0',
            'class' => 'string|max:50',
            'base_fare' => 'sometimes|required|numeric|min:0',
            'taxes' => 'numeric|min:0',
            'currency' => 'string|size:3',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 400);
        }

        $flight->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Flight updated successfully',
            'data' => $flight
        ]);
    }

    public function destroyFlight($id)
    {
        $flight = Flight::findOrFail($id);
        $flight->delete();

        return response()->json([
            'success' => true,
            'message' => 'Flight deleted successfully'
        ]);
    }

    // ==========================================
    // CARS CRUD
    // ==========================================

    public function indexCars(Request $request)
    {
        $query = Car::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('rental_company', 'like', "%{$search}%")
                  ->orWhere('car_model', 'like', "%{$search}%")
                  ->orWhere('car_class', 'like', "%{$search}%")
                  ->orWhere('pickup_location', 'like', "%{$search}%");
            });
        }

        $cars = $query->orderBy('id', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $cars
        ]);
    }

    public function storeCar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'rental_company' => 'required|string|max:255',
            'car_model' => 'required|string|max:255',
            'car_class' => 'required|string|max:100',
            'pickup_location' => 'required|string|max:255',
            'dropoff_location' => 'required|string|max:255',
            'transmission' => 'string|max:100',
            'fuel_type' => 'string|max:100',
            'mileage' => 'string|max:100',
            'daily_rate' => 'required|numeric|min:0',
            'currency' => 'string|size:3',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 400);
        }

        $car = Car::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Car created successfully',
            'data' => $car
        ], 201);
    }

    public function updateCar(Request $request, $id)
    {
        $car = Car::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'rental_company' => 'sometimes|required|string|max:255',
            'car_model' => 'sometimes|required|string|max:255',
            'car_class' => 'sometimes|required|string|max:100',
            'pickup_location' => 'sometimes|required|string|max:255',
            'dropoff_location' => 'sometimes|required|string|max:255',
            'transmission' => 'string|max:100',
            'fuel_type' => 'string|max:100',
            'mileage' => 'string|max:100',
            'daily_rate' => 'sometimes|required|numeric|min:0',
            'currency' => 'string|size:3',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 400);
        }

        $car->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Car updated successfully',
            'data' => $car
        ]);
    }

    public function destroyCar($id)
    {
        $car = Car::findOrFail($id);
        $car->delete();

        return response()->json([
            'success' => true,
            'message' => 'Car deleted successfully'
        ]);
    }

    // ==========================================
    // EXPERIENCES CRUD
    // ==========================================

    public function indexExperiences(Request $request)
    {
        $query = Experience::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        $experiences = $query->orderBy('id', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $experiences
        ]);
    }

    public function storeExperience(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'location' => 'required|string|max:255',
            'duration' => 'string|max:100',
            'rating' => 'numeric|between:0,5',
            'price_per_ticket' => 'required|numeric|min:0',
            'currency' => 'string|size:3',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 400);
        }

        $experience = Experience::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Experience created successfully',
            'data' => $experience
        ], 201);
    }

    public function updateExperience(Request $request, $id)
    {
        $experience = Experience::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'category' => 'sometimes|required|string|max:100',
            'location' => 'sometimes|required|string|max:255',
            'duration' => 'string|max:100',
            'rating' => 'numeric|between:0,5',
            'price_per_ticket' => 'sometimes|required|numeric|min:0',
            'currency' => 'string|size:3',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 400);
        }

        $experience->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Experience updated successfully',
            'data' => $experience
        ]);
    }

    public function destroyExperience($id)
    {
        $experience = Experience::findOrFail($id);
        $experience->delete();

        return response()->json([
            'success' => true,
            'message' => 'Experience deleted successfully'
        ]);
    }

    // ==========================================
    // TRANSFERS CRUD
    // ==========================================

    public function indexTransfers(Request $request)
    {
        $query = Transfer::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('transfer_type', 'like', "%{$search}%")
                  ->orWhere('pickup_location', 'like', "%{$search}%")
                  ->orWhere('dropoff_location', 'like', "%{$search}%");
            });
        }

        $transfers = $query->orderBy('id', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $transfers
        ]);
    }

    public function storeTransfer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'transfer_type' => 'required|string|max:100',
            'name' => 'required|string|max:255',
            'vehicle' => 'required|string|max:255',
            'capacity' => 'required|string|max:100',
            'pickup_location' => 'required|string|max:255',
            'dropoff_location' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'currency' => 'string|size:3',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 400);
        }

        $transfer = Transfer::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Transfer created successfully',
            'data' => $transfer
        ], 201);
    }

    public function updateTransfer(Request $request, $id)
    {
        $transfer = Transfer::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'transfer_type' => 'sometimes|required|string|max:100',
            'name' => 'sometimes|required|string|max:255',
            'vehicle' => 'sometimes|required|string|max:255',
            'capacity' => 'sometimes|required|string|max:100',
            'pickup_location' => 'sometimes|required|string|max:255',
            'dropoff_location' => 'sometimes|required|string|max:255',
            'price' => 'sometimes|required|numeric|min:0',
            'currency' => 'string|size:3',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 400);
        }

        $transfer->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Transfer updated successfully',
            'data' => $transfer
        ]);
    }

    public function destroyTransfer($id)
    {
        $transfer = Transfer::findOrFail($id);
        $transfer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Transfer deleted successfully'
        ]);
    }
}
