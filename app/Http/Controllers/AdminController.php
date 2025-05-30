<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Service;
use App\Models\Application;
use App\Models\Beneficiary;
use App\Models\AicsDetail;
use App\Models\Assistance;
use App\Models\PwdDetail;
use App\Models\SoloParentDetail;
use App\Models\ContactEmergency;
use App\Models\Barangay;  // Import Barangay model if needed
use Livewire\WithPagination;
use App\Models\BenefitReceived;
use App\Models\FamilyBackground;
use App\Models\FamilyComposition;
use App\Models\ForSpdOrSpoUseOnly;
use App\Models\Log;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;



class AdminController extends Controller
{
    use WithPagination;
    public function addview()
    {
        return view('admin.add_service');
    }

    public function upload(Request $request)
    {
        $service = new Service; // Correct capitalization for model name

        $image = $request->file('file');
        $imagename = time() . '.' . $image->getClientOriginalExtension();
        $request->file('file')->move('serviceimage', $imagename);

        $service->image = $imagename;
        $service->name = $request->name;
        $service->description = $request->description;

        $service->save();
        return redirect()->back()->with('message', 'Service added successfully!');
    }

    public function showservices()
    {
        $data = Service::all();
        return view('admin.showservices', compact('data'));
    }
    //ope
    public function showservicesope()
    {
        $data = Service::all();
        return view('operator.allservices', compact('data'));
    }

    public function deleteservices($id)
    {
        $data = Service::find($id);
        $data->delete();
        return redirect()->back();
    }

    public function editservices($id)
    {
        $data = Service::find($id);
        return view('admin.editservices', compact('data'));
    }

    public function updateservice(Request $request, $id)
    {
        $service = Service::find($id);
        $service->name = $request->name;
        $service->description = $request->description;

        $image = $request->file('file');

        if ($image) {
            $imagename = time() . '.' . $image->getClientOriginalExtension();
            $request->file('file')->move('serviceimage', $imagename);
            $service->image = $imagename;
        }

        $service->save();
        return redirect()->back()->with('message', 'Service details updated successfully');
    }

    // Display all applications
    public function displayapplication(Request $request)
    {
        $filter = $request->query('filter');

        $status = $request->query('status');

        $from = $request->query('from');

        $to = $request->query('to');

        $search = $request->query('search');

        $data = Beneficiary::with(['approvedBy', 'acceptedBy', 'aicsDetails', 'pwdDetails', 'soloParentDetails', 'service', 'barangay'])->where(function ($query) use ($filter) {
            $query->where('status', $filter ?? 'approved')->orWhere('status', 'accepted');
        })
            ->where(function ($query) use ($status, $from, $to, $search) {
                if ($status) {
                    $query->where('status', $status);
                }
                if ($from && $to) {
                    $query->where(function ($q) use ($from, $to) {
                        $q->whereBetween('appearance_date', [$from, $to])
                            ->orWhere(function ($q) use ($from, $to) {
                                $q->whereNull('appearance_date')
                                    ->whereBetween('created_at', [$from, $to]);
                            });
                    });
                }
                if ($search) {
                    $query->where('last_name', "LIKE", "%{$search}%")
                        ->orWhere('first_name', "LIKE", "%{$search}%")
                        ->orWhere('middle_name', "LIKE", "%{$search}%")
                        ->orWhere('suffix', "LIKE", "%{$search}%")
                        ->orWhere('phone', "LIKE", "%{$search}%")
                        ->orWhere('appearance_date', "LIKE", "%{$search}%")
                        ->orWhere('status', "LIKE", "%{$search}%")
                        ->orWhereHas('service', function ($subQuery) use ($search) {
                            $subQuery->where('name', "LIKE", "%{$search}%");
                        })
                        ->orWhereHas('service', function ($subQuery) use ($search) {
                            $subQuery->where('name', "LIKE", "%{$search}%");
                        })
                        ->orWhereHas('barangay', function ($subQuery) use ($search) {
                            $subQuery->where('name', "LIKE", "%{$search}%");
                        });
                }
            })
            ->orderBy('created_at', 'desc')->paginate(10);
        return view('admin.displayapplication', compact('data'));
    }

    // Add Beneficiary View
    public function addbeneficiaryview()
    {
        $services = Service::all(); // Fetch all services
        $barangays = Barangay::all(); // Fetch all barangays if needed
        return view('admin.add_beneficiary', compact('services', 'barangays')); // Pass barangays to the view
    }

    // Show all beneficiaries
    public function showbeneficiary()
    {
        // Eager load the barangay relationship
        $beneficiaries = Beneficiary::with('barangay')->get();
        $services = Service::all();
        $barangays = Barangay::all(); // Fetch all barangays if needed
        return view('admin.showbeneficiary', compact('barangays', 'services', 'beneficiaries'));
    }


    // Delete beneficiary
    public function deletebeneficiaries($id)
    {
        $beneficiary = Beneficiary::findOrFail($id);

        if (Auth::user()->usertype === 'admin') {
            $url = '/showbeneficiaries_admin';
        } else if (Auth::user()->usertype === 'operator') {
            $url = '/showbeneficiaries_operator';
        } else {
            $url = '/display_beneficiaries';
        }
        $beneficiary->delete();

        return redirect($url)->with('success', 'Beneficiary with ID: ' . $id . ' deleted successfully');
    }

    // Edit beneficiary
    // Edit beneficiary method
    public function editBeneficiaries($id)
    {
        // Retrieve the beneficiary by ID
        $beneficiary = Beneficiary::find($id);

        // If beneficiary doesn't exist, redirect with an error
        if (!$beneficiary) {
            return redirect()->route('admin.beneficiaries.index')->with('error', 'Beneficiary not found');
        }

        // Fetch all services for the program dropdown
        $services = Service::all();

        // Fetch all barangays if needed
        $barangays = Barangay::all();

        // Pass the beneficiary, services, and barangays to the edit view
        return view('admin.editbeneficiaries', compact('beneficiary', 'services', 'barangays'));
    }

    public function gisMapping()
    {
        $services = Service::all(); // Fetch all services (programs)
        $barangays = Barangay::distinct()->pluck('barangay'); // Fetch distinct barangay names
        $beneficiaries = Beneficiary::where('status', 'released')->get(); // Fetch only released beneficiaries

        return view('admin.gis', compact('services', 'barangays', 'beneficiaries'));
    }



    public function showReports()
    {
        // Fetching all services (assuming you have a Service model)
        $services = Service::all();

        // Fetching all barangays (assuming you have a Barangay model)
        $barangays = Barangay::all();

        // Pass the data to the view
        return view('admin.reports', compact('services', 'barangays'));
    }



    // Update beneficiary details
    public function uploadbeneficiary(Request $request)
    {
        $beneficiary = new Beneficiary;

        $beneficiary->first_name = $request->first_name;
        $beneficiary->middle_name = $request->middle_name;
        $beneficiary->last_name = $request->last_name;
        $beneficiary->suffix = $request->suffix;  // Added suffix
        $beneficiary->email = $request->email;
        $beneficiary->phone = $request->phone;
        $beneficiary->program_enrolled = $request->program_enrolled;
        $beneficiary->barangay_id = $request->barangay;
        $beneficiary->latitude = $request->latitude;
        $beneficiary->longitude = $request->longitude;
        $beneficiary->date_of_birth = $request->date_of_birth;
        $beneficiary->age = $request->age;
        $beneficiary->gender = $request->gender;
        $beneficiary->place_of_birth = $request->place_of_birth;  // Added place of birth
        $beneficiary->civil_status = $request->civil_status;
        $beneficiary->educational_attainment = $request->educational_attainment;  // Added educational attainment
        $beneficiary->occupation = $request->occupation;  // Added occupation
        $beneficiary->religion = $request->religion;  // Added religion
        $beneficiary->monthly_income = $request->monthly_income;
        $beneficiary->id_number = $request->id_number;  // Added ID number

        $beneficiary->save();

        return redirect()->back()->with('message', 'Beneficiary added successfully!');
    }

    // Controller method to update the beneficiary
    public function updateBeneficiary(Request $request, $id)
    {
        // Hanapin ang beneficiary gamit ang ID
        $beneficiary = Beneficiary::find($id);

        if (!$beneficiary) {
            return redirect()->back()->with('message', 'Beneficiary not found.');
        }

        // I-update ang mga fields
        $beneficiary->first_name = $request->first_name;
        $beneficiary->middle_name = $request->middle_name;
        $beneficiary->last_name = $request->last_name;
        $beneficiary->suffix = $request->suffix;
        $beneficiary->email = $request->email;
        $beneficiary->phone = $request->phone;
        $beneficiary->program_enrolled = $request->program_enrolled;
        $beneficiary->barangay_id = $request->barangay;
        $beneficiary->latitude = $request->latitude;
        $beneficiary->longitude = $request->longitude;
        $beneficiary->date_of_birth = $request->date_of_birth;
        $beneficiary->age = $request->age;
        $beneficiary->gender = $request->gender;
        $beneficiary->place_of_birth = $request->place_of_birth;
        $beneficiary->civil_status = $request->civil_status;
        $beneficiary->educational_attainment = $request->educational_attainment;
        $beneficiary->occupation = $request->occupation;
        $beneficiary->religion = $request->religion;
        $beneficiary->monthly_income = $request->monthly_income;
        $beneficiary->id_number = $request->id_number;

        // Save the updated record
        $beneficiary->save();

        return redirect('/showbeneficiary')->with('message', 'Beneficiary updated successfully.');
    }



    public function beneficiary_search(Request $request)
    {
        $search = $request->search;

        // Use a JOIN to search by service and barangay name
        $beneficiaries = Beneficiary::with('barangay', 'service')
            ->where(function ($query) use ($search) {
                // Search beneficiaries by first, middle, last name, email, and phone
                $query->where('first_name', 'LIKE', '%' . $search . '%')
                    ->orWhere('middle_name', 'LIKE', '%' . $search . '%')
                    ->orWhere('last_name', 'LIKE', '%' . $search . '%')
                    ->orWhere('email', 'LIKE', '%' . $search . '%')
                    ->orWhere('phone', 'LIKE', '%' . $search . '%')
                    // Search by program_enrolled (service name)
                    ->orWhereHas('service', function ($query) use ($search) {
                        $query->where('name', 'LIKE', '%' . $search . '%');
                    })
                    // Search by barangay (barangay name)
                    ->orWhereHas('barangay', function ($query) use ($search) {
                        $query->where('name', 'LIKE', '%' . $search . '%');
                    });
            })
            ->get();


        return view('admin.showbeneficiary', compact('beneficiaries'));
    }

    public function application_search(Request $request)
    {
        $search = $request->search;

        $data = Application::with('service')
            ->where(function ($query) use ($search) {
                // Search by name (full name) or individual fields
                $query->where('name', 'LIKE', '%' . $search . '%')
                    ->orWhere('email', 'LIKE', '%' . $search . '%')
                    ->orWhere('phone', 'LIKE', '%' . $search . '%')
                    ->orWhereHas('service', function ($query) use ($search) {
                        $query->where('name', 'LIKE', '%' . $search . '%');
                    })

                    ->orWhere('status', 'LIKE', '%' . $search . '%')
                    // Search for employee_name, include 'pending' or null (no employee assigned)
                    // Include search on date_applied
                    ->orWhere('date_applied', 'LIKE', '%' . $search . '%')
                    ->orWhere('employee_name', 'LIKE', '%' . $search . '%');
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('admin.displayapplication', compact('data'));
    }

    // dropdown admin
    public function showOscaadmin()
    {
        $beneficiaries = Beneficiary::whereHas('service', function ($query) {
            $query->where('name', 'OSCA(Office of Senior Citizens)');
        })->get();

        return view('dropdownadm.osca', compact('beneficiaries'));
    }

    public function showPwdadmin()
    {
        $beneficiaries = Beneficiary::whereHas('service', function ($query) {
            $query->where('name', 'PWD(Persons with Disabilities)');
        })->get();

        return view('dropdownadm.pwd', compact('beneficiaries'));
    }

    public function showSoloParentadmin()
    {
        $beneficiaries = Beneficiary::whereHas('service', function ($query) {
            $query->where('name', 'Solo Parent');
        })->get();

        return view('dropdownadm.solo_parent', compact('beneficiaries'));
    }
    public function showAicsadmin()
    {
        $beneficiaries = Beneficiary::whereHas('service', function ($query) {
            $query->where('name', 'AICS(Assistance to Individuals in Crisis)');
        })->get();

        return view('dropdownadm.aics', compact('beneficiaries'));
    }



    public function newBenefitsshow(Request $request)
    {
        $beneficiaries = Beneficiary::with('barangay', 'benefitsReceived')->get();
        $services = Service::all();
        $barangays = Barangay::all(); // Fetch all barangays if needed

        $assistanceList = Assistance::with(['benefitReceiveds', 'benefitReceiveds.assistance', 'benefitReceiveds.beneficiary'])
            ->orderBy('created_at', 'desc') // Sorting by latest first
            ->paginate(10);

        return view('admin.newbenefits', compact('assistanceList', 'services', 'barangays', 'beneficiaries'));
    }


    public function filterBeneficiaries(Request $request)
    {
        $barangay = $request->barangay;

        $query = Beneficiary::query();

        if ($barangay) {
            $query->where('barangay_id', $barangay);
        }

        $beneficiaries = $query->get();

        return response()->json($beneficiaries);
    }

    public function generateBeneficiaries(Request $request)
    {
        $beneficiaryIds = $request->input('beneficiary_ids');
        $assistanceName = $request->input('name_of_assistance');

        if (!empty($beneficiaryIds)) {
            // Logic to associate the beneficiaries with assistance if needed
        }

        return response()->json(['success' => true, 'message' => 'Beneficiaries generated successfully.']);
    }

    public function getBeneficiaries(Request $request)
    {
        $minAge = $request->input('min_age');
        $maxAge = $request->input('max_age');
        $serviceIds = $request->input('service_ids');
        $limit = $request->input('limit');
        $barangayIds = $request->input('barangay_ids');
        $assistanceIds = $request->input('assistance2_ids');

        $query = Beneficiary::query()->whereIn('status', ['Released', 'Approved'])->where('is_deceased', false);
        $query2 = Beneficiary::query()->where('is_deceased', false);

        if ($minAge) {
            $query->where('age', '>=', $minAge);
        }

        if ($maxAge) {
            $query->where('age', '<=', $maxAge);
        }
        if ($serviceIds) {
            $query->whereIn('program_enrolled', $serviceIds); // Use 'program_enrolled' instead of 'service_id'
        }
        if ($barangayIds) {
            $query->whereIn('barangay_id', $barangayIds); // Use 'barangay_id' for barangay filter
        }
        if ($assistanceIds) {
            $excludedBeneficiaries = BenefitReceived::whereIn('assistance_id', $assistanceIds)
                ->pluck('beneficiary_id');

            $query->whereNotIn('id', $excludedBeneficiaries);

            $query2->whereIn('id', $excludedBeneficiaries);
        }

        // Eager load the benefitsReceived relationship for the beneficiaries
        $beneficiaries = $query->with(['benefitsReceived', 'barangay'])->take($limit)->get()
            ->reject(function ($beneficiary) {
                return $beneficiary->expiredBeneficiary();
            });


        if (!$assistanceIds) {
            $excludedBeneficiariesData = collect();
        } else {
            $excludedBeneficiariesData = $query2->with(['benefitsReceived', 'barangay'])
                ->get()
                ->reject(function ($beneficiary) {
                    return $beneficiary->expiredBeneficiary();
                })->map(function ($beneficiary) {
                    return [
                        'id' => $beneficiary->id,
                        'name' => $beneficiary->full_name,
                        'age' => $beneficiary->age,
                        'barangay' => $beneficiary->barangay->name,
                    ];
                });
        }

        // Retrieve all services for the dropdown
        $services = Service::all();
        $barangays = Barangay::all();

        $totalBeneficiaries = $beneficiaries->count();

        $beneficiaryIds = $beneficiaries->pluck('id');
        $excludedBeneficiaryIds = $excludedBeneficiariesData->pluck('id');

        $beneficiaryData = $beneficiaries->map(function ($beneficiary) {
            return [
                'name' => $beneficiary->full_name,
                'age' => $beneficiary->age,
                'barangay' => $beneficiary->barangay->name,
            ];
        });

        return response()->json([
            'totalBeneficiaries' => $totalBeneficiaries,
            'beneficiaries' => $beneficiaryData,
            'services' => $services,
            'barangays' => $barangays,
            'beneficiary_ids' => $beneficiaryIds,
            'showExcludedBeneficiaries' => $excludedBeneficiariesData,
            'excluded_beneficiary_ids' => $excludedBeneficiaryIds

        ], 200);
        // return view('admin.newbenefits', compact('totalBeneficiaries', 'beneficiaries', 'services', 'barangays', 'nameOfAssistance'));
    }



    public function addAssistanceToBeneficiaries(Request $request)
    {
        // Retrieve the form data
        $beneficiaryIds = $request->input('beneficiary_ids');

        // Check if beneficiaryIds is an array and not empty
        if (is_array($beneficiaryIds) && count($beneficiaryIds) > 0) {
            $nameOfAssistance = $request->input('name_of_assistance');
            $typeOfAssistance = $request->input('type_of_assistance');
            $amount = $request->input('amount');
            $dateReceived = null; // Default value for date_received

            foreach ($beneficiaryIds as $beneficiaryId) {
                BenefitReceived::create([
                    'beneficiary_id' => $beneficiaryId,
                    'name_of_assistance' => $nameOfAssistance,
                    'type_of_assistance' => $typeOfAssistance,
                    'amount' => $amount,
                    'date_received' => $dateReceived, // Will be null for now
                ]);
            }

            return redirect()->back()->with('success', 'Assistance added successfully!');
        } else {
            return redirect()->back()->with('error', 'No beneficiaries selected.');
        }
    }

    public function AllbenefitsReceived()
    {
        // Eager load the barangay and benefitsReceived relationships
        $beneficiaries = Beneficiary::with('barangay', 'benefitsReceived')->get();
        $services = Service::all();
        $barangays = Barangay::all(); // Fetch all barangays if needed

        // Fetch distinct name_of_assistance values
        $assistanceList = BenefitReceived::distinct()->pluck('name_of_assistance')->toArray();

        return view('admin.all_benefitsreceived', compact('barangays', 'services', 'beneficiaries', 'assistanceList'));
    }


    public function filterBenefitsReceived(Request $request)
    {
        $nameOfAssistance = $request->input('name_of_assistance');

        $beneficiaries = Beneficiary::whereHas('benefitsReceived', function ($query) use ($nameOfAssistance) {
            $query->where('name_of_assistance', $nameOfAssistance);
        })
            ->with([
                'benefitsReceived' => function ($query) use ($nameOfAssistance) {
                    $query->where('name_of_assistance', $nameOfAssistance);
                },
                'service',
                'barangay'
            ])
            ->get();

        $assistanceList = BenefitReceived::distinct()->pluck('name_of_assistance');

        return view('admin.all_benefitsreceived', compact('beneficiaries', 'assistanceList', 'nameOfAssistance'));
    }


    public function Inventory()
    {
        // Eager load the barangay and benefitsReceived relationships
        $beneficiaries = Beneficiary::with('barangay', 'benefitsReceived')->get();
        $services = Service::all();
        $barangays = Barangay::all(); // Fetch all barangays if needed

        $allAssistanceTypes = BenefitReceived::all();  // All available assistance types
        // Fetch distinct name_of_assistance values
        $assistanceList = BenefitReceived::distinct()->pluck('name_of_assistance')->toArray();

        return view('admin.inventory', compact('barangays', 'services', 'beneficiaries', 'assistanceList', 'allAssistanceTypes'));
    }


    public function filterforInventory(Request $request)
    {
        $nameOfAssistance = $request->input('name_of_assistance');

        $beneficiaries = Beneficiary::whereHas('benefitsReceived', function ($query) use ($nameOfAssistance) {
            $query->where('name_of_assistance', $nameOfAssistance)
                ->whereNotNull('date_received'); // Exclude rows without date_received
        })
            ->with([
                'benefitsReceived' => function ($query) use ($nameOfAssistance) {
                    $query->where('name_of_assistance', $nameOfAssistance)
                        ->whereNotNull('date_received'); // Exclude rows without date_received
                },
                'service',
                'barangay'
            ])
            ->get();

        $assistanceList = BenefitReceived::distinct()->pluck('name_of_assistance');

        return view('admin.inventory', compact('beneficiaries', 'assistanceList', 'nameOfAssistance'));
    }

    public function markAsDeceased($id)
    {
        // Debugging: Check if the ID exists
        $beneficiary = Beneficiary::find($id);
        if (!$beneficiary) {
            return back()->with('error', 'Beneficiary not found.');
        }
        // Debugging: Confirm insertion into the deceased table
        $beneficiary->update([
            'is_deceased' => true
        ]);
        // Debugging: Success
        return redirect()->back()->with('success', "Beneficiary marked as deceased successfully.");
    }

    public function Alldeceased()
    {
        $services = Service::all();
        $barangays = Barangay::all(); // Fetch all barangays if needed
        $deceased = DB::table('deceased')
            ->join('barangays', 'deceased.barangay_id', '=', 'barangays.id')
            ->select('deceased.*', 'barangays.name as barangay_name')
            ->get();
        // Pass the data to the view
        return view('admin.deceased', compact('deceased'));
    }

    public function adminApproval(Request $request, $id)
    {
        $application = Beneficiary::findOrFail($id);

        $aicsType = AicsDetail::where('beneficiary_id', $application->id)->first();

        $soloParentDetail = SoloParentDetail::where('beneficiary_id', $application->id)->first();

        $application->update([
            'approved_by' => Auth::user()->id,
            'approved_at' => now(),
            'status' => 'approved'
        ]);

        if ($application->service->id === 4) {
            $request->validate([
                'case_no' => ['required'],
                'new_or_old' => ['required'],
                'problem_presented' => ['required'],
                'findings' => ['required'],
                'action_taken' => ['required'],
            ]);
            $aicsType->update([
                'case_no' => $request->case_no,
                'new_or_old' => $request->new_or_old,
                'problem_presented' => $request->problem_presented,
                'findings' => $request->findings,
                'action_taken' => $request->action_taken,
            ]);
        }
        if ($application->service->id === 3) {
            $request->validate([
                'status' => ['required'],
                'id_type' => ['required'],
                'card_number' => ['required'],
                'category' => ['required'],
            ]);

            ForSpdOrSpoUseOnly::create([
                'solo_parent_detail_id' => $soloParentDetail->id,
                'status' => $request->status,
                'id_type' => $request->id_type,
                'card_number' => $request->card_number,
                'category' => $request->category,
            ]);
        }

        return back()->with('success', 'Application approved successfully!');
    }

    public function adminRejection(Request $request, $id)
    {

        $application = Beneficiary::findOrFail($id);

        $request->validate([
            'cancellation_reason' => ['required', 'min:1', 'max:255', 'string']
        ], [
            'cancellation_reason.required' => 'Please state a reason first before rejecting.',
        ]);

        $application->update([
            'status' => 'rejected',
            'cancellation_reason' => $request->cancellation_reason,
        ]);

        return back()->with('success', 'Application rejected successfully!');
    }

    protected function Barangay()
    {
        $barangays = Barangay::all();

        return $barangays;
    }


    public function addOsca()
    {
        $barangays = $this->Barangay();
        return view('admin.add-beneficiaries.add-osca', compact('barangays'));
    }

    public function editOsca($id)
    {
        $beneficiary = Beneficiary::with('user.familyCompositions')->findOrFail($id);
        $barangays = $this->Barangay();
        return view('admin.edit-beneficiaries.edit-osca', compact('barangays', 'beneficiary'));
    }

    public function storeOsca(Request $request)
    {

        $oscaId = Service::where('name', 'OSCA(Office of Senior Citizens)')->first()->id;

        $request->validate([
            'last_name' => ['required', 'min:1', 'max:255', 'string'],
            'first_name' => ['required', 'min:1', 'max:255', 'string'],
            'place_of_birth' => ['required', 'min:1', 'max:255', 'string'],
            'civil_status' => ['required', 'min:1', 'max:255', 'string'],
            'date_of_birth' => ['required', 'min:1', 'max:255', 'string'],
            'gender' => ['required', 'min:1', 'max:255', 'string'],
            'barangay' => ['required', 'min:1', 'max:255', 'string'],
            'educational_attainment' => ['required', 'min:1', 'max:255', 'string'],
            'occupation' => ['required', 'min:1', 'max:255', 'string'],
            'annual_income' => ['required', 'min:1', 'max:255', 'string'],
            'other_skills' => ['required', 'min:1', 'max:255', 'string'],
            'name.*' => ['required', 'min:1', 'max:255', 'string'],
            'relationship.*' => ['required', 'min:1', 'max:255', 'string'],
            'civil_status_fc.*' => ['required', 'min:1', 'max:255', 'string'],
            'age_fc.*' => ['required', 'min:1', 'max:255', 'string'],
            'occupation_fc.*' => ['required', 'min:1', 'max:255', 'string'],
            'income.*' => ['required', 'min:1', 'max:255', 'string'],
            'phone' => ['required', 'min:1', 'max:255', 'string'],

        ]);

        $user = User::firstOrCreate([
            'last_name'     => $request->last_name,
            'first_name'    => $request->first_name,
        ], [
            'email'         => trim(strtolower("{$request->last_name}{$request->first_name}@gmail.com")),
            'phone'         => $request->phone,
            'age'           => $request->age,
            'gender'        => $request->gender,
            'date_of_birth' => $request->date_of_birth,
            'barangay_id'   => $request->barangay,
            'password'      => 'mswd_default_password'
        ]);

        $beneficiary = Beneficiary::create([
            'program_enrolled' => $oscaId,
            'last_name' => $request->last_name,
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name,
            'place_of_birth' => $request->place_of_birth,
            'civil_status' => $request->civil_status,
            'date_of_birth' => $request->date_of_birth,
            'gender' => $request->gender,
            'barangay_id' => $request->barangay,
            'educational_attainment' => $request->educational_attainment,
            'occupation' => $request->occupation,
            'annual_income' => $request->annual_income,
            'other_skills' => $request->other_skills,
            'age' => $request->age,
            'status' => 'approved',
            'accepted_by' => Auth::id(),
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'phone' => $request->phone,
            'user_id' => $user->id,
            'email'         => trim(strtolower("{$request->last_name}{$request->first_name}@gmail.com")),
        ]);

        foreach ($request->name as $index => $name) {
            FamilyComposition::updateOrCreate([
                'user_id' => $user->id
            ], [
                'name' => $name,
                'relationship' => $request->relationship[$index],
                'civil_status' => $request->civil_status_fc[$index],
                'age' => $request->age_fc[$index],
                'occupation' => $request->occupation_fc[$index],
                'income' => $request->income[$index],
            ]);
        }
        if (Auth::user()->usertype === 'admin') {
            $url = '/showbeneficiaries_admin';
        } else if (Auth::user()->usertype === 'operator') {
            $url = '/showbeneficiaries_operator';
        } else {
            $url = '/display_beneficiaries';
        }

        return redirect($url)->with('success', 'Beneficiary added successfully!');
    }

    public function updateOsca(Request $request, $id)
    {

        $beneficiary = Beneficiary::findOrFail($id);

        $request->validate([
            'last_name' => ['required', 'min:1', 'max:255', 'string'],
            'first_name' => ['required', 'min:1', 'max:255', 'string'],
            'place_of_birth' => ['required', 'min:1', 'max:255', 'string'],
            'civil_status' => ['required', 'min:1', 'max:255', 'string'],
            'date_of_birth' => ['required', 'min:1', 'max:255', 'string'],
            'gender' => ['required', 'min:1', 'max:255', 'string'],
            'barangay' => ['required', 'min:1', 'max:255', 'string'],
            'educational_attainment' => ['required', 'min:1', 'max:255', 'string'],
            'occupation' => ['required', 'min:1', 'max:255', 'string'],
            'annual_income' => ['required', 'min:1', 'max:255', 'string'],
            'other_skills' => ['required', 'min:1', 'max:255', 'string'],
            'name.*' => ['required', 'min:1', 'max:255', 'string'],
            'relationship.*' => ['required', 'min:1', 'max:255', 'string'],
            'civil_status_fc.*' => ['required', 'min:1', 'max:255', 'string'],
            'age_fc.*' => ['required', 'min:1', 'max:255', 'string'],
            'occupation_fc.*' => ['required', 'min:1', 'max:255', 'string'],
            'income.*' => ['required', 'min:1', 'max:255', 'string'],
            'phone' => ['required', 'min:1', 'max:255', 'string'],

        ]);

        $original = $beneficiary->getOriginal();

        $beneficiary->update([
            'last_name' => $request->last_name,
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name,
            'place_of_birth' => $request->place_of_birth,
            'civil_status' => $request->civil_status,
            'date_of_birth' => $request->date_of_birth,
            'gender' => $request->gender,
            'barangay_id' => $request->barangay,
            'educational_attainment' => $request->educational_attainment,
            'occupation' => $request->occupation,
            'annual_income' => $request->annual_income,
            'other_skills' => $request->other_skills,
            'age' => $request->age,
            'phone' => $request->phone,
        ]);

        $logEntry = 'Updated beneficiary record with the following changes: ';
        $changedData = [];

        if ($original['last_name'] !== $request->last_name) {
            $changedData[] = "Last Name: {$original['last_name']} → {$request->last_name}";
        }

        if ($original['first_name'] !== $request->first_name) {
            $changedData[] = "First Name: {$original['first_name']} → {$request->first_name}";
        }

        if ($original['middle_name'] !== $request->middle_name) {
            $changedData[] = "Middle Name: {$original['middle_name']} → {$request->middle_name}";
        }

        if ($original['place_of_birth'] !== $request->place_of_birth) {
            $changedData[] = "Place of Birth: {$original['place_of_birth']} → {$request->place_of_birth}";
        }

        if ($original['civil_status'] !== $request->civil_status) {
            $changedData[] = "Civil Status: {$original['civil_status']} → {$request->civil_status}";
        }

        if ($original['date_of_birth'] !== $request->date_of_birth) {
            $changedData[] = "Date of Birth: {$original['date_of_birth']} → {$request->date_of_birth}";
        }

        if ($original['gender'] !== $request->gender) {
            $changedData[] = "Gender: {$original['gender']} → {$request->gender}";
        }

        if ($original['barangay_id'] !== $request->barangay) {
            $changedData[] = "Barangay: {$original['barangay_id']} → {$request->barangay}";
        }

        if ($original['educational_attainment'] !== $request->educational_attainment) {
            $changedData[] = "Educational Attainment: {$original['educational_attainment']} → {$request->educational_attainment}";
        }

        if ($original['occupation'] !== $request->occupation) {
            $changedData[] = "Occupation: {$original['occupation']} → {$request->occupation}";
        }

        if ($original['annual_income'] !== $request->annual_income) {
            $changedData[] = "Annual Income: {$original['annual_income']} → {$request->annual_income}";
        }

        if ($original['other_skills'] !== $request->other_skills) {
            $changedData[] = "Other Skills: {$original['other_skills']} → {$request->other_skills}";
        }

        if ($original['age'] !== $request->age) {
            $changedData[] = "Age: {$original['age']} → {$request->age}";
        }

        if ($original['phone'] !== $request->phone) {
            $changedData[] = "Phone: {$original['phone']} → {$request->phone}";
        }

        if (count($changedData) > 0) {
            $logEntry .= implode(', ', $changedData);

            Log::create([
                'user_id' => Auth::id(),
                'beneficiary_id' => $beneficiary->id,
                'log_entry' => $logEntry,
            ]);
        }

        if ($request->has('family_composition_data')) {
            foreach ($request->family_composition_data as $index => $data) {
                FamilyComposition::create([
                    'user_id' => $request->user_id,
                    'name' => $request->name[$index],
                    'relationship' => $request->relationship[$index],
                    'civil_status' => $request->civil_status_fc[$index],
                    'age' => $request->age_fc[$index],
                    'occupation' => $request->occupation_fc[$index],
                    'income' => $request->income[$index],
                ]);
            }
        } else {

            foreach ($beneficiary->user->familyCompositions as $fc) {
                FamilyComposition::updateOrCreate([
                    'user_id' => $request->user_id,
                    'id' => $fc->id
                ], [
                    'name' => $request->name[$fc->id],
                    'relationship' => $request->relationship[$fc->id],
                    'civil_status' => $request->civil_status_fc[$fc->id],
                    'age' => $request->age_fc[$fc->id],
                    'occupation' => $request->occupation_fc[$fc->id],
                    'income' => $request->income[$fc->id],
                ]);
            }
        }

        if (Auth::user()->usertype === 'admin') {
            $url = '/displayapplication';
        } else if (Auth::user()->usertype === 'operator') {
            $url = '/showbeneficiaries_operator';
        } else {
            $url = '/display_beneficiaries';
        }

        return redirect($url)->with('success', 'Beneficiary updated successfully!');
    }


    public function addAics()
    {
        $barangays = $this->Barangay();
        return view('admin.add-beneficiaries.add-aics', compact('barangays'));
    }

    public function editAics($id)
    {
        $beneficiary = Beneficiary::with(['user.familyCompositions', 'aicsDetails'])->findOrFail($id);
        $barangays = $this->Barangay();
        return view('admin.edit-beneficiaries.edit-aics', compact('barangays', 'beneficiary'));
    }

    public function storeAics(Request $request)
    {

        $aicsId = Service::where('name', 'AICS(Assistance to Individuals in Crisis)')->first()->id;

        $request->validate([
            'last_name' => ['required', 'min:1', 'max:255', 'string'],
            'first_name' => ['required', 'min:1', 'max:255', 'string'],
            'place_of_birth' => ['required', 'min:1', 'max:255', 'string'],
            'date_of_birth' => ['required', 'min:1', 'max:255', 'string'],
            'religion' => ['required', 'min:1', 'max:255', 'string'],
            'civil_status' => ['required', 'min:1', 'max:255', 'string'],
            'barangay' => ['required', 'min:1', 'max:255', 'string'],
            'occupation' => ['required', 'min:1', 'max:255', 'string'],
            'educational_attainment' => ['required', 'min:1', 'max:255', 'string'],
            'name.*' => ['required', 'min:1', 'max:255', 'string'],
            'relationship.*' => ['required', 'min:1', 'max:255', 'string'],
            'age_fc.*' => ['required', 'min:1', 'max:255', 'string'],
            'gender_fc.*' => ['required', 'min:1', 'max:255', 'string'],
            'civil_status_fc.*' => ['required', 'min:1', 'max:255', 'string'],
            'occupation_fc.*' => ['required', 'min:1', 'max:255', 'string'],
            'educational_attainment_fc.*' => ['required', 'min:1', 'max:255', 'string'],
            'case_no' => ['required', 'min:1', 'max:255', 'string'],
            'new_or_old' => ['required', 'min:1', 'max:255', 'string'],
            'date_applied' => ['required', 'min:1', 'max:255', 'string'],
            'source_of_referral' => ['required', 'min:1', 'max:255', 'string'],
            'problem_presented' => ['required', 'min:1', 'max:255', 'string'],
            'findings' => ['required', 'min:1', 'max:255', 'string'],
            'action_taken' => ['required', 'min:1', 'max:255', 'string'],
            'phone' => ['required', 'min:1', 'max:255', 'string'],

        ]);



        $user = User::firstOrCreate([
            'last_name'     => $request->last_name,
            'first_name'    => $request->first_name,
        ], [
            'email'         => trim(strtolower("{$request->last_name}{$request->first_name}@gmail.com")),
            'phone'         => $request->phone,
            'age'           => $request->age,
            'date_of_birth' => $request->date_of_birth,
            'barangay_id'   => $request->barangay,
            'password'      => 'mswd_default_password'
        ]);

        $beneficiary = Beneficiary::create([
            'program_enrolled' => $aicsId,
            'last_name' => $request->last_name,
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name,
            'place_of_birth' => $request->place_of_birth,
            'date_of_birth' => $request->date_of_birth,
            'religion' => $request->religion,
            'civil_status' => $request->civil_status,
            'barangay_id' => $request->barangay,
            'occupation' => $request->occupation,
            'educational_attainment' => $request->educational_attainment,
            'age' => $request->age,
            'status' => 'approved',
            'accepted_by' => Auth::id(),
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'phone' => $request->phone,
            'user_id' => $user->id,
            'email'         => trim(strtolower("{$request->last_name}{$request->first_name}@gmail.com")),
        ]);

        foreach ($request->name as $index => $name) {
            FamilyComposition::updateOrCreate([
                'user_id' => $user->id
            ], [
                'name' => $name,
                'relationship' => $request->relationship[$index],
                'civil_status' => $request->civil_status_fc[$index],
                'age' => $request->age_fc[$index],
                'occupation' => $request->occupation_fc[$index],
                'income' => $request->income[$index],
            ]);
        }

        AicsDetail::create([
            'beneficiary_id' => $beneficiary->id,
            'case_no' => $request->case_no,
            'new_or_old' => $request->new_or_old,
            'date_applied' => $request->date_applied,
            'source_of_referral' => $request->source_of_referral,
            'problem_presented' => $request->problem_presented,
            'findings' => $request->findings,
            'action_taken' => $request->action_taken,
        ]);
        if (Auth::user()->usertype === 'admin') {
            $url = '/showbeneficiaries_admin';
        } else if (Auth::user()->usertype === 'operator') {
            $url = '/showbeneficiaries_operator';
        } else {
            $url = '/display_beneficiaries';
        }

        return redirect($url)->with('success', 'Beneficiary added successfully!');
    }

    public function updateAics(Request $request, $id)
    {

        $beneficiary = Beneficiary::findOrFail($id);

        $request->validate([
            'last_name' => ['required', 'min:1', 'max:255', 'string'],
            'first_name' => ['required', 'min:1', 'max:255', 'string'],
            'place_of_birth' => ['required', 'min:1', 'max:255', 'string'],
            'date_of_birth' => ['required', 'min:1', 'max:255', 'string'],
            'religion' => ['required', 'min:1', 'max:255', 'string'],
            'civil_status' => ['required', 'min:1', 'max:255', 'string'],
            'barangay' => ['required', 'min:1', 'max:255', 'string'],
            'occupation' => ['required', 'min:1', 'max:255', 'string'],
            'educational_attainment' => ['required', 'min:1', 'max:255', 'string'],
            'name.*' => ['required', 'min:1', 'max:255', 'string'],
            'relationship.*' => ['required', 'min:1', 'max:255', 'string'],
            'age_fc.*' => ['required', 'min:1', 'max:255', 'string'],
            'gender_fc.*' => ['required', 'min:1', 'max:255', 'string'],
            'civil_status_fc.*' => ['required', 'min:1', 'max:255', 'string'],
            'occupation_fc.*' => ['required', 'min:1', 'max:255', 'string'],
            'educational_attainment_fc.*' => ['required', 'min:1', 'max:255', 'string'],
            'case_no' => ['required', 'min:1', 'max:255', 'string'],
            'new_or_old' => ['required', 'min:1', 'max:255', 'string'],
            'date_applied' => ['required', 'min:1', 'max:255', 'string'],
            'source_of_referral' => ['required', 'min:1', 'max:255', 'string'],
            'problem_presented' => ['required', 'min:1', 'max:255', 'string'],
            'findings' => ['required', 'min:1', 'max:255', 'string'],
            'action_taken' => ['required', 'min:1', 'max:255', 'string'],
            'phone' => ['required', 'min:1', 'max:255', 'string'],

        ]);

        $original = $beneficiary->getOriginal();

        $beneficiary->update([
            'last_name' => $request->last_name,
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name,
            'place_of_birth' => $request->place_of_birth,
            'date_of_birth' => $request->date_of_birth,
            'religion' => $request->religion,
            'civil_status' => $request->civil_status,
            'barangay_id' => $request->barangay,
            'occupation' => $request->occupation,
            'educational_attainment' => $request->educational_attainment,
            'age' => $request->age,
            'phone' => $request->phone,
        ]);

        $logEntry = 'Updated beneficiary record with the following changes: ';
        $changedData = [];

        // Compare and log only updated fields
        if ($original['last_name'] !== $request->last_name) {
            $changedData[] = "Last Name: {$original['last_name']} → {$request->last_name}";
        }

        if ($original['first_name'] !== $request->first_name) {
            $changedData[] = "First Name: {$original['first_name']} → {$request->first_name}";
        }

        if ($original['middle_name'] !== $request->middle_name) {
            $changedData[] = "Middle Name: {$original['middle_name']} → {$request->middle_name}";
        }

        if ($original['place_of_birth'] !== $request->place_of_birth) {
            $changedData[] = "Place of Birth: {$original['place_of_birth']} → {$request->place_of_birth}";
        }

        if ($original['date_of_birth'] !== $request->date_of_birth) {
            $changedData[] = "Date of Birth: {$original['date_of_birth']} → {$request->date_of_birth}";
        }

        if ($original['religion'] !== $request->religion) {
            $changedData[] = "Religion: {$original['religion']} → {$request->religion}";
        }

        if ($original['civil_status'] !== $request->civil_status) {
            $changedData[] = "Civil Status: {$original['civil_status']} → {$request->civil_status}";
        }

        if ($original['barangay_id'] !== $request->barangay) {
            $changedData[] = "Barangay: {$original['barangay_id']} → {$request->barangay}";
        }

        if ($original['occupation'] !== $request->occupation) {
            $changedData[] = "Occupation: {$original['occupation']} → {$request->occupation}";
        }

        if ($original['educational_attainment'] !== $request->educational_attainment) {
            $changedData[] = "Educational Attainment: {$original['educational_attainment']} → {$request->educational_attainment}";
        }

        if ($original['age'] !== $request->age) {
            $changedData[] = "Age: {$original['age']} → {$request->age}";
        }

        if ($original['phone'] !== $request->phone) {
            $changedData[] = "Phone: {$original['phone']} → {$request->phone}";
        }

        if (count($changedData) > 0) {
            $logEntry .= implode(', ', $changedData);

            Log::create([
                'user_id' => Auth::id(),
                'beneficiary_id' => $beneficiary->id,
                'log_entry' => $logEntry,
            ]);
        }

        if ($request->has('family_composition_data')) {
            foreach ($request->family_composition_data as $index => $data) {
                FamilyComposition::create([
                    'user_id' => $request->user_id,
                    'name' => $request->name[$index],
                    'relationship' => $request->relationship[$index],
                    'age' => $request->age_fc[$index],
                    'gender' => $request->gender_fc[$index],
                    'civil_status' => $request->civil_status_fc[$index],
                    'occupation' => $request->occupation_fc[$index],
                    'educational' => $request->educational_attainment_fc[$index],
                ]);
            }
        } else {
            foreach ($beneficiary->user->familyCompositions as $index => $fc) {
                FamilyComposition::updateOrCreate([
                    'user_id' => $request->user_id,
                    'id' => $fc->id
                ], [
                    'name' => $request->name[$fc->id],
                    'relationship' => $request->relationship[$fc->id],
                    'age' => $request->age_fc[$fc->id],
                    'gender' => $request->gender_fc[$fc->id],
                    'civil_status' => $request->civil_status_fc[$fc->id],
                    'occupation' => $request->occupation_fc[$fc->id],
                    'educational' => $request->educational_attainment_fc[$fc->id],
                ]);
            }
        }

        AicsDetail::updateOrCreate(
            [
                'beneficiary_id' => $beneficiary->id,
            ],
            [
                'case_no' => $request->case_no,
                'new_or_old' => $request->new_or_old,
                'date_applied' => $request->date_applied,
                'source_of_referral' => $request->source_of_referral,
                'problem_presented' => $request->problem_presented,
                'findings' => $request->findings,
                'action_taken' => $request->action_taken,
            ]
        );
        if (Auth::user()->usertype === 'admin') {
            $url = '/displayapplication';
        } else if (Auth::user()->usertype === 'operator') {
            $url = '/showbeneficiaries_operator';
        } else {
            $url = '/display_beneficiaries';
        }

        return redirect($url)->with('success', 'Beneficiary updated successfully!');
    }

    public function addSoloParent()
    {
        $barangays = $this->Barangay();
        return view('admin.add-beneficiaries.add-solo-parent', compact('barangays'));
    }

    public function editSoloParent($id)
    {
        $beneficiary = Beneficiary::with(['user.familyCompositions', 'soloParentDetails', 'contactEmergencies'])->findOrFail($id);
        $barangays = $this->Barangay();
        return view('admin.edit-beneficiaries.edit-solo-parent', compact('barangays', 'beneficiary'));
    }


    public function storeSoloParent(Request $request)
    {

        $soloParentId = Service::where('name', 'Solo Parent')->first()->id;

        $request->validate([
            'last_name' => ['required', 'min:1', 'max:255', 'string'],
            'first_name' => ['required', 'min:1', 'max:255', 'string'],
            'place_of_birth' => ['required', 'min:1', 'max:255', 'string'],
            'date_of_birth' => ['required', 'min:1', 'max:255', 'string'],
            'religion' => ['required', 'min:1', 'max:255', 'string'],
            'gender' => ['required', 'min:1', 'max:255', 'string'],
            'barangay' => ['required', 'min:1', 'max:255', 'string'],
            'civil_status' => ['required', 'min:1', 'max:255', 'string'],
            'annual_income' => ['required', 'min:1', 'max:255', 'string'],
            'educational_attainment' => ['required', 'min:1', 'max:255', 'string'],
            'occupation' => ['required', 'min:1', 'max:255', 'string'],
            'phone' => ['required', 'min:1', 'max:255', 'string'],
            'name.*' => ['required', 'min:1', 'max:255', 'string'],
            'relationship.*' => ['required', 'min:1', 'max:255', 'string'],
            'birthday.*' => ['required', 'min:1', 'max:255', 'string'],
            'age_fc.*' => ['required', 'min:1', 'max:255', 'string'],
            'civil_status_fc.*' => ['required', 'min:1', 'max:255', 'string'],
            'occupation_fc.*' => ['required', 'min:1', 'max:255', 'string'],
            'educational_attainment_fc.*' => ['required', 'min:1', 'max:255', 'string'],
            'income.*' => ['required', 'min:1', 'max:255', 'string'],
            'company_agency' => ['required', 'min:1', 'max:255', 'string'],
            'four_ps_beneficiary' => ['required', 'min:1', 'max:255', 'string'],
            'indigenous_person' => ['required', 'min:1', 'max:255', 'string'],
            'classification_circumtances' => ['required', 'min:1', 'max:255', 'string'],
            'needs_problems' => ['required', 'min:1', 'max:255', 'string'],
            'emergency_name' => ['required', 'min:1', 'max:255', 'string'],
            'emergency_address' => ['required', 'min:1', 'max:255', 'string'],
            'emergency_contact_number' => ['required', 'min:1', 'max:255', 'string'],

        ]);

        $user = User::firstOrCreate([
            'last_name'     => $request->last_name,
            'first_name'    => $request->first_name,
        ], [
            'email'         => trim(strtolower("{$request->last_name}{$request->first_name}@gmail.com")),
            'phone'         => $request->phone,
            'age'           => $request->age,
            'gender'        => $request->gender,
            'date_of_birth' => $request->date_of_birth,
            'barangay_id'   => $request->barangay,
            'password'      => 'mswd_default_password'
        ]);

        $beneficiary = Beneficiary::create([
            'program_enrolled' => $soloParentId,
            'last_name' => $request->last_name,
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name,
            'place_of_birth' => $request->place_of_birth,
            'date_of_birth' => $request->date_of_birth,
            'religion' => $request->religion,
            'gender' => $request->gender,
            'barangay_id' => $request->barangay,
            'civil_status' => $request->civil_status,
            'annual_income' => $request->annual_income,
            'educational_attainment' => $request->educational_attainment,
            'occupation' => $request->occupation,
            'phone' => $request->phone,
            'age' => $request->age,
            'status' => 'approved',
            'accepted_by' => Auth::id(),
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'user_id' => $user->id,
            'email'         => trim(strtolower("{$request->last_name}{$request->first_name}@gmail.com")),
        ]);

        foreach ($request->name as $index => $name) {
            FamilyComposition::updateOrCreate([
                'user_id' => $user->id
            ], [
                'name' => $name,
                'relationship' => $request->relationship[$index],
                'civil_status' => $request->civil_status_fc[$index],
                'age' => $request->age_fc[$index],
                'occupation' => $request->occupation_fc[$index],
                'income' => $request->income[$index],
            ]);
        }

        SoloParentDetail::create([
            'beneficiary_id' => $beneficiary->id,
            'company_agency' => $request->company_agency,
            'four_ps_beneficiary' => $request->four_ps_beneficiary,
            'indigenous_person' => $request->indigenous_person,
            'classification_circumtances' => $request->classification_circumtances,
            'needs_problems' => $request->needs_problems,
        ]);

        ContactEmergency::create([
            'beneficiary_id' => $beneficiary->id,
            'name' => $request->emergency_name,
            'address' => $request->emergency_address,
            'contact_number' => $request->emergency_contact_number,

        ]);
        if (Auth::user()->usertype === 'admin') {
            $url = '/showbeneficiaries_admin';
        } else if (Auth::user()->usertype === 'operator') {
            $url = '/showbeneficiaries_operator';
        } else {
            $url = '/display_beneficiaries';
        }

        return redirect($url)->with('success', 'Beneficiary added successfully!');
    }

    public function updateSoloParent(Request $request, $id)
    {
        $beneficiary = Beneficiary::findOrFail($id);

        $request->validate([
            'last_name' => ['required', 'min:1', 'max:255', 'string'],
            'first_name' => ['required', 'min:1', 'max:255', 'string'],
            'place_of_birth' => ['required', 'min:1', 'max:255', 'string'],
            'date_of_birth' => ['required', 'min:1', 'max:255', 'string'],
            'religion' => ['required', 'min:1', 'max:255', 'string'],
            'gender' => ['required', 'min:1', 'max:255', 'string'],
            'barangay' => ['required', 'min:1', 'max:255', 'string'],
            'civil_status' => ['required', 'min:1', 'max:255', 'string'],
            'annual_income' => ['required', 'min:1', 'max:255', 'string'],
            'educational_attainment' => ['required', 'min:1', 'max:255', 'string'],
            'occupation' => ['required', 'min:1', 'max:255', 'string'],
            'phone' => ['required', 'min:1', 'max:255', 'string'],
            'name.*' => ['required', 'min:1', 'max:255', 'string'],
            'relationship.*' => ['required', 'min:1', 'max:255', 'string'],
            'birthday.*' => ['required', 'min:1', 'max:255', 'string'],
            'age_fc.*' => ['required', 'min:1', 'max:255', 'string'],
            'civil_status_fc.*' => ['required', 'min:1', 'max:255', 'string'],
            'occupation_fc.*' => ['required', 'min:1', 'max:255', 'string'],
            'educational_attainment_fc.*' => ['required', 'min:1', 'max:255', 'string'],
            'income.*' => ['required', 'min:1', 'max:255', 'string'],
            'company_agency' => ['required', 'min:1', 'max:255', 'string'],
            'four_ps_beneficiary' => ['required', 'min:1', 'max:255', 'string'],
            'indigenous_person' => ['required', 'min:1', 'max:255', 'string'],
            'classification_circumtances' => ['required', 'min:1', 'max:255', 'string'],
            'needs_problems' => ['required', 'min:1', 'max:255', 'string'],
            'emergency_name' => ['required', 'min:1', 'max:255', 'string'],
            'emergency_address' => ['required', 'min:1', 'max:255', 'string'],
            'emergency_contact_number' => ['required', 'min:1', 'max:255', 'string'],

        ]);

        $original = $beneficiary->getOriginal();

        $beneficiary->update([
            'last_name' => $request->last_name,
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name,
            'place_of_birth' => $request->place_of_birth,
            'date_of_birth' => $request->date_of_birth,
            'religion' => $request->religion,
            'gender' => $request->gender,
            'barangay_id' => $request->barangay,
            'civil_status' => $request->civil_status,
            'annual_income' => $request->annual_income,
            'educational_attainment' => $request->educational_attainment,
            'occupation' => $request->occupation,
            'phone' => $request->phone,
            'age' => $request->age,
        ]);

        $logEntry = 'Updated beneficiary record with the following changes: ';
        $changedData = [];

        if ($original['last_name'] !== $request->last_name) {
            $changedData[] = "Last Name: {$original['last_name']} → {$request->last_name}";
        }

        if ($original['first_name'] !== $request->first_name) {
            $changedData[] = "First Name: {$original['first_name']} → {$request->first_name}";
        }

        if ($original['middle_name'] !== $request->middle_name) {
            $changedData[] = "Middle Name: {$original['middle_name']} → {$request->middle_name}";
        }

        if ($original['place_of_birth'] !== $request->place_of_birth) {
            $changedData[] = "Place of Birth: {$original['place_of_birth']} → {$request->place_of_birth}";
        }

        if ($original['date_of_birth'] !== $request->date_of_birth) {
            $changedData[] = "Date of Birth: {$original['date_of_birth']} → {$request->date_of_birth}";
        }

        if ($original['religion'] !== $request->religion) {
            $changedData[] = "Religion: {$original['religion']} → {$request->religion}";
        }

        if ($original['gender'] !== $request->gender) {
            $changedData[] = "Gender: {$original['gender']} → {$request->gender}";
        }

        if ($original['barangay_id'] !== $request->barangay) {
            $changedData[] = "Barangay: {$original['barangay_id']} → {$request->barangay}";
        }

        if ($original['civil_status'] !== $request->civil_status) {
            $changedData[] = "Civil Status: {$original['civil_status']} → {$request->civil_status}";
        }

        if ($original['annual_income'] !== $request->annual_income) {
            $changedData[] = "Annual Income: {$original['annual_income']} → {$request->annual_income}";
        }

        if ($original['educational_attainment'] !== $request->educational_attainment) {
            $changedData[] = "Educational Attainment: {$original['educational_attainment']} → {$request->educational_attainment}";
        }

        if ($original['occupation'] !== $request->occupation) {
            $changedData[] = "Occupation: {$original['occupation']} → {$request->occupation}";
        }

        if ($original['phone'] !== $request->phone) {
            $changedData[] = "Phone: {$original['phone']} → {$request->phone}";
        }

        if ($original['age'] !== $request->age) {
            $changedData[] = "Age: {$original['age']} → {$request->age}";
        }

        if (count($changedData) > 0) {
            $logEntry .= implode(', ', $changedData);

            Log::create([
                'user_id' => Auth::id(),
                'beneficiary_id' => $beneficiary->id,
                'log_entry' => $logEntry,
            ]);
        }

        if ($request->has('family_composition_data')) {
            foreach ($request->family_composition_data as $index => $data) {
                FamilyComposition::create([
                    'user_id' => $request->user_id,
                    'name' => $request->name[$index],
                    'relationship' => $request->relationship[$index],
                    'birthday' => $request->birthday[$index],
                    'age' => $request->age_fc[$index],
                    'civil_status' => $request->civil_status_fc[$index],
                    'occupation' => $request->occupation_fc[$index],
                    'educational' => $request->educational_attainment_fc[$index],
                    'income' => $request->income[$index],
                ]);
            }
        } else {
            foreach ($beneficiary->user->familyCompositions as $index => $fc) {
                FamilyComposition::updateOrCreate([
                    'user_id' => $request->user_id,
                    'id' => $fc->id
                ], [
                    'name' => $request->name[$fc->id],
                    'relationship' => $request->relationship[$fc->id],
                    'birthday' => $request->birthday[$fc->id],
                    'age' => $request->age_fc[$fc->id],
                    'civil_status' => $request->civil_status_fc[$fc->id],
                    'occupation' => $request->occupation_fc[$fc->id],
                    'educational' => $request->educational_attainment_fc[$fc->id],
                    'income' => $request->income[$fc->id],
                ]);
            }
        }

        SoloParentDetail::updateOrCreate([
            'beneficiary_id' => $beneficiary->id,
        ], [
            'beneficiary_id' => $beneficiary->id,
            'company_agency' => $request->company_agency,
            'four_ps_beneficiary' => $request->four_ps_beneficiary,
            'indigenous_person' => $request->indigenous_person,
            'classification_circumtances' => $request->classification_circumtances,
            'needs_problems' => $request->needs_problems,
        ]);

        ContactEmergency::updateOrCreate([
            'beneficiary_id' => $beneficiary->id,
        ], [
            'beneficiary_id' => $beneficiary->id,
            'name' => $request->emergency_name,
            'address' => $request->emergency_address,
            'contact_number' => $request->emergency_contact_number,

        ]);
        if (Auth::user()->usertype === 'admin') {
            $url = '/displayapplication';
        } else if (Auth::user()->usertype === 'operator') {
            $url = '/showbeneficiaries_operator';
        } else {
            $url = '/display_beneficiaries';
        }

        return redirect($url)->with('success', 'Beneficiary updated successfully!');
    }

    public function addPwd()
    {
        $barangays = $this->Barangay();
        return view('admin.add-beneficiaries.add-pwd', compact('barangays'));
    }

    public function editPwd($id)
    {
        $beneficiary = Beneficiary::with(['pwdDetails', 'familyBackgrounds'])->findOrFail($id);
        $barangays = $this->Barangay();
        return view('admin.edit-beneficiaries.edit-pwd', compact('barangays', 'beneficiary'));
    }


    public function storePwd(Request $request)
    {

        $pwdId = Service::where('name', 'PWD(Persons with Disabilities)')->first()->id;

        $request->validate([
            'last_name' => ['required', 'min:1', 'max:255', 'string'],
            'first_name' => ['required', 'min:1', 'max:255', 'string'],
            'place_of_birth' => ['required', 'min:1', 'max:255', 'string'],
            'date_of_birth' => ['required', 'min:1', 'max:255', 'string'],
            'religion' => ['required', 'min:1', 'max:255', 'string'],
            'gender' => ['required', 'min:1', 'max:255', 'string'],
            'barangay' => ['required', 'min:1', 'max:255', 'string'],
            'civil_status' => ['required', 'min:1', 'max:255', 'string'],
            'annual_income' => ['required', 'min:1', 'max:255', 'string'],
            'educational_attainment' => ['required', 'min:1', 'max:255', 'string'],
            'occupation' => ['required', 'min:1', 'max:255', 'string'],
            'phone' => ['required', 'min:1', 'max:255', 'string'],
            'application_type' => ['required', 'min:1', 'max:255', 'string'],
            'pwd_number' => ['nullable', 'required_if:application_type,Renewal', 'min:1', 'max:255', 'string'],
            'blood_type' => ['required', 'min:1', 'max:255', 'string'],
            'type_of_disability' => ['required', 'min:1', 'max:255', 'string'],
            'cause_of_disability' => ['required', 'min:1', 'max:255', 'string'],
            'acquired' => ['nullable', 'required_if:cause_of_disability,Acquired', 'min:1', 'max:255', 'string'],
            'other_acquired' => ['nullable', 'required_if:acquired,Other', 'min:1', 'max:255', 'string'],
            'other_cause_of_disability' => ['nullable', 'required_if:cause_of_disability,Other', 'min:1', 'max:255', 'string'],
            'congenital_inborn' => ['nullable', 'required_if:cause_of_disability,Congenital/Inborn', 'min:1', 'max:255', 'string'],
            'other_congenital_inborn' => ['nullable', 'required_if:congenital_inborn,Other', 'min:1', 'max:255', 'string'],
            'house_no_and_street' => ['required', 'min:1', 'max:255', 'string'],
            'municipality' => ['required', 'min:1', 'max:255', 'string'],
            'province' => ['required', 'min:1', 'max:255', 'string'],
            'region' => ['required', 'min:1', 'max:255', 'string'],
            'landline_no' => ['required', 'min:1', 'max:255', 'string'],
            'email_address' => ['required', 'min:1', 'max:255', 'string'],
            'status_of_employment' => ['required', 'min:1', 'max:255', 'string'],
            'category_of_employment' => ['required', 'min:1', 'max:255', 'string'],
            'types_of_employment' => ['required', 'min:1', 'max:255', 'string'],
            'organization_affiliated' => ['required', 'min:1', 'max:255', 'string'],
            'contact_person' => ['required', 'min:1', 'max:255', 'string'],
            'office_address' => ['required', 'min:1', 'max:255', 'string'],
            'tel_no' => ['required', 'min:1', 'max:255', 'string'],
            'sss_no' => ['required', 'min:1', 'max:255', 'string'],
            'gsis_no' => ['required', 'min:1', 'max:255', 'string'],
            'pag_ibig_no' => ['required', 'min:1', 'max:255', 'string'],
            'psn_no' => ['required', 'min:1', 'max:255', 'string'],
            'philhealth_no' => ['required', 'min:1', 'max:255', 'string'],
            'accomplished_by' => ['required', 'min:1', 'max:255', 'string'],
        ]);



        $user = User::firstOrCreate([
            'last_name'     => $request->last_name,
            'first_name'    => $request->first_name,
        ], [
            'email'         => trim(strtolower("{$request->last_name}{$request->first_name}@gmail.com")),
            'phone'         => $request->phone,
            'age'           => $request->age,
            'gender'        => $request->gender,
            'date_of_birth' => $request->date_of_birth,
            'barangay_id'   => $request->barangay,
            'password'      => 'mswd_default_password'
        ]);

        $beneficiary = Beneficiary::create([
            'program_enrolled' => $pwdId,
            'last_name' => $request->last_name,
            'first_name' => $request->first_name,
            'place_of_birth' => $request->place_of_birth,
            'date_of_birth' => $request->date_of_birth,
            'religion' => $request->religion,
            'gender' => $request->gender,
            'barangay_id' => $request->barangay,
            'civil_status' => $request->civil_status,
            'annual_income' => $request->annual_income,
            'educational_attainment' => $request->educational_attainment,
            'occupation' => $request->occupation,
            'phone' => $request->phone,
            'age' => $request->age,
            'status' => 'approved',
            'accepted_by' => Auth::id(),
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'user_id' => $user->id,
            'email'         => trim(strtolower("{$request->last_name}{$request->first_name}@gmail.com")),
        ]);

        $acquired = $request->acquired === 'Other' ? 'Other, ' . $request->other_acquired : $request->acquired;
        $cause_of_disability = $request->cause_of_disability === 'Other' ? 'Other, ' . $request->other_cause_of_disability : $request->cause_of_disability;
        $congenital_inborn = $request->congenital_inborn === 'Other' ? 'Other, ' . $request->other_congenital_inborn : $request->congenital_inborn;

        PwdDetail::create([
            'beneficiary_id' => $beneficiary->id,
            'application_type' => $request->application_type,
            'pwd_number' => $request->pwd_number,
            'blood_type' => $request->blood_type,
            'type_of_disability' => $request->type_of_disability,
            'acquired' => $acquired,
            'cause_of_disability' => $cause_of_disability,
            'congenital_inborn' => $congenital_inborn,
            'house_no_and_street' => $request->house_no_and_street,
            'municipality' => $request->municipality,
            'province' => $request->province,
            'region' => $request->region,
            'landline_no' => $request->landline_no,
            'email_address' => $request->email_address,
            'status_of_employment' => $request->status_of_employment,
            'category_of_employment' => $request->category_of_employment,
            'types_of_employment' => $request->types_of_employment,
            'organization_affiliated' => $request->organization_affiliated,
            'contact_person' => $request->contact_person,
            'office_address' => $request->office_address,
            'tel_no' => $request->tel_no,
            'sss_no' => $request->sss_no,
            'gsis_no' => $request->gsis_no,
            'pag_ibig_no' => $request->pag_ibig_no,
            'psn_no' => $request->psn_no,
            'philhealth_no' => $request->philhealth_no,
            'accomplished_by' => $request->accomplished_by,
        ]);

        FamilyBackground::create([
            'beneficiary_id' => $beneficiary->id,
            'father_name' => $request->father_name,
            'father_occupation' => $request->father_occupation,
            'father_phone' => $request->father_phone,
            'mother_name' => $request->mother_name,
            'mother_occupation' => $request->mother_occupation,
            'mother_phone' => $request->mother_phone,
            'guardian_name' => $request->guardian_name,
            'guardian_occupation' => $request->guardian_occupation,
            'guardian_phone' => $request->guardian_phone,
        ]);
        if (Auth::user()->usertype === 'admin') {
            $url = '/showbeneficiaries_admin';
        } else if (Auth::user()->usertype === 'operator') {
            $url = '/showbeneficiaries_operator';
        } else {
            $url = '/display_beneficiaries';
        }

        return redirect($url)->with('success', 'Beneficiary added successfully!');
    }

    public function updatePwd(Request $request, $id)
    {
        $beneficiary = Beneficiary::findOrFail($id);

        $request->validate([
            'last_name' => ['required', 'min:1', 'max:255', 'string'],
            'first_name' => ['required', 'min:1', 'max:255', 'string'],
            'place_of_birth' => ['required', 'min:1', 'max:255', 'string'],
            'date_of_birth' => ['required', 'min:1', 'max:255', 'string'],
            'religion' => ['required', 'min:1', 'max:255', 'string'],
            'gender' => ['required', 'min:1', 'max:255', 'string'],
            'barangay' => ['required', 'min:1', 'max:255', 'string'],
            'civil_status' => ['required', 'min:1', 'max:255', 'string'],
            'annual_income' => ['required', 'min:1', 'max:255', 'string'],
            'educational_attainment' => ['required', 'min:1', 'max:255', 'string'],
            'occupation' => ['required', 'min:1', 'max:255', 'string'],
            'phone' => ['required', 'min:1', 'max:255', 'string'],
            'application_type' => ['required', 'min:1', 'max:255', 'string'],
            'pwd_number' => ['required', 'min:1', 'max:255', 'string'],
            'blood_type' => ['required', 'min:1', 'max:255', 'string'],
            'type_of_disability' => ['required', 'min:1', 'max:255', 'string'],
            'cause_of_disability' => ['required', 'min:1', 'max:255', 'string'],
            'acquired' => ['nullable', 'required_if:cause_of_disability,Acquired', 'min:1', 'max:255', 'string'],
            'other_acquired' => ['nullable', 'required_if:acquired,Other', 'min:1', 'max:255', 'string'],
            'other_cause_of_disability' => ['nullable', 'required_if:cause_of_disability,Other', 'min:1', 'max:255', 'string'],
            'congenital_inborn' => ['nullable', 'required_if:cause_of_disability,Congenital/Inborn', 'min:1', 'max:255', 'string'],
            'other_congenital_inborn' => ['nullable', 'required_if:congenital_inborn,Other', 'min:1', 'max:255', 'string'],
            'house_no_and_street' => ['required', 'min:1', 'max:255', 'string'],
            'municipality' => ['required', 'min:1', 'max:255', 'string'],
            'province' => ['required', 'min:1', 'max:255', 'string'],
            'region' => ['required', 'min:1', 'max:255', 'string'],
            'landline_no' => ['required', 'min:1', 'max:255', 'string'],
            'email_address' => ['required', 'min:1', 'max:255', 'string'],
            'status_of_employment' => ['required', 'min:1', 'max:255', 'string'],
            'category_of_employment' => ['required', 'min:1', 'max:255', 'string'],
            'types_of_employment' => ['required', 'min:1', 'max:255', 'string'],
            'organization_affiliated' => ['required', 'min:1', 'max:255', 'string'],
            'contact_person' => ['required', 'min:1', 'max:255', 'string'],
            'office_address' => ['required', 'min:1', 'max:255', 'string'],
            'tel_no' => ['required', 'min:1', 'max:255', 'string'],
            'sss_no' => ['required', 'min:1', 'max:255', 'string'],
            'gsis_no' => ['required', 'min:1', 'max:255', 'string'],
            'pag_ibig_no' => ['required', 'min:1', 'max:255', 'string'],
            'psn_no' => ['required', 'min:1', 'max:255', 'string'],
            'philhealth_no' => ['required', 'min:1', 'max:255', 'string'],
            'accomplished_by' => ['required', 'min:1', 'max:255', 'string'],
        ]);

        $original = $beneficiary->getOriginal();

        $beneficiary->update([
            'last_name' => $request->last_name,
            'first_name' => $request->first_name,
            'place_of_birth' => $request->place_of_birth,
            'date_of_birth' => $request->date_of_birth,
            'religion' => $request->religion,
            'gender' => $request->gender,
            'barangay_id' => $request->barangay,
            'civil_status' => $request->civil_status,
            'annual_income' => $request->annual_income,
            'educational_attainment' => $request->educational_attainment,
            'occupation' => $request->occupation,
            'phone' => $request->phone,
            'age' => $request->age,
        ]);

        $logEntry = 'Updated beneficiary record with the following changes: ';
        $changedData = [];

        if ($original['last_name'] !== $request->last_name) {
            $changedData[] = "Last Name: {$original['last_name']} → {$request->last_name}";
        }

        if ($original['first_name'] !== $request->first_name) {
            $changedData[] = "First Name: {$original['first_name']} → {$request->first_name}";
        }

        if ($original['place_of_birth'] !== $request->place_of_birth) {
            $changedData[] = "Place of Birth: {$original['place_of_birth']} → {$request->place_of_birth}";
        }

        if ($original['date_of_birth'] !== $request->date_of_birth) {
            $changedData[] = "Date of Birth: {$original['date_of_birth']} → {$request->date_of_birth}";
        }

        if ($original['religion'] !== $request->religion) {
            $changedData[] = "Religion: {$original['religion']} → {$request->religion}";
        }

        if ($original['gender'] !== $request->gender) {
            $changedData[] = "Gender: {$original['gender']} → {$request->gender}";
        }

        if ($original['barangay_id'] !== $request->barangay) {
            $changedData[] = "Barangay: {$original['barangay_id']} → {$request->barangay}";
        }

        if ($original['civil_status'] !== $request->civil_status) {
            $changedData[] = "Civil Status: {$original['civil_status']} → {$request->civil_status}";
        }

        if ($original['annual_income'] !== $request->annual_income) {
            $changedData[] = "Annual Income: {$original['annual_income']} → {$request->annual_income}";
        }

        if ($original['educational_attainment'] !== $request->educational_attainment) {
            $changedData[] = "Educational Attainment: {$original['educational_attainment']} → {$request->educational_attainment}";
        }

        if ($original['occupation'] !== $request->occupation) {
            $changedData[] = "Occupation: {$original['occupation']} → {$request->occupation}";
        }

        if ($original['phone'] !== $request->phone) {
            $changedData[] = "Phone: {$original['phone']} → {$request->phone}";
        }

        if ($original['age'] !== $request->age) {
            $changedData[] = "Age: {$original['age']} → {$request->age}";
        }

        if (count($changedData) > 0) {
            $logEntry .= implode(', ', $changedData);

            Log::create([
                'user_id' => Auth::id(),
                'beneficiary_id' => $beneficiary->id,
                'log_entry' => $logEntry,
            ]);
        }

        $acquired = $request->acquired === 'Other' ? 'Other, ' . $request->other_acquired : $request->acquired;
        $cause_of_disability = $request->cause_of_disability === 'Other' ? 'Other, ' . $request->other_cause_of_disability : $request->cause_of_disability;
        $congenital_inborn = $request->congenital_inborn === 'Other' ? 'Other, ' . $request->other_congenital_inborn : $request->congenital_inborn;

        PwdDetail::updateOrCreate([
            'beneficiary_id' => $beneficiary->id,
        ], [
            'application_type' => $request->application_type,
            'pwd_number' => $request->pwd_number,
            'blood_type' => $request->blood_type,
            'type_of_disability' => $request->type_of_disability,
            'acquired' => $acquired,
            'cause_of_disability' => $cause_of_disability,
            'congenital_inborn' => $congenital_inborn,
            'house_no_and_street' => $request->house_no_and_street,
            'municipality' => $request->municipality,
            'province' => $request->province,
            'region' => $request->region,
            'landline_no' => $request->landline_no,
            'email_address' => $request->email_address,
            'status_of_employment' => $request->status_of_employment,
            'category_of_employment' => $request->category_of_employment,
            'types_of_employment' => $request->types_of_employment,
            'organization_affiliated' => $request->organization_affiliated,
            'contact_person' => $request->contact_person,
            'office_address' => $request->office_address,
            'tel_no' => $request->tel_no,
            'sss_no' => $request->sss_no,
            'gsis_no' => $request->gsis_no,
            'pag_ibig_no' => $request->pag_ibig_no,
            'psn_no' => $request->psn_no,
            'philhealth_no' => $request->philhealth_no,
            'accomplished_by' => $request->accomplished_by,
        ]);

        FamilyBackground::updateOrCreate([

            'beneficiary_id' => $beneficiary->id,
        ], [
            'father_name' => $request->father_name,
            'father_occupation' => $request->father_occupation,
            'father_phone' => $request->father_phone,
            'mother_name' => $request->mother_name,
            'mother_occupation' => $request->mother_occupation,
            'mother_phone' => $request->mother_phone,
            'guardian_name' => $request->guardian_name,
            'guardian_occupation' => $request->guardian_occupation,
            'guardian_phone' => $request->guardian_phone,
        ]);
        if (Auth::user()->usertype === 'admin') {
            $url = '/displayapplication';
        } else if (Auth::user()->usertype === 'operator') {
            $url = '/showbeneficiaries_operator';
        } else {
            $url = '/display_beneficiaries';
        }

        return redirect($url)->with('success', 'Beneficiary updated successfully!');
    }

    public function releasedUser(Request $request, $id)
    {
        $beneficiary = Beneficiary::findOrFail($id);

        $applicationController = new ApplicationController();

        $message = "Your request has been approved. Please proceed to the MSWD office for further instructions.\n\n-FROM MSWD ABUYOG.\n" . now()->format('F d, Y');

        $applicationController->sendSMSNotification($beneficiary->phone, $message);

        $beneficiary->update([
            'status' => 'released'
        ]);

        if ($beneficiary->service->id == 4) {
            $assistance = Assistance::create([
                'name_of_assistance' => 'Aics',
                'amount' => $request->amount,
                'date_received' => now(),
                'type_of_assistance' => $beneficiary->aicsDetails[0]->type_of_assistance
            ]);

            BenefitReceived::create([
                'beneficiary_id' => $beneficiary->id,
                'assistance_id' => $assistance->id,
                'status' => 'Received',
            ]);
        }

        return back()->with('success', 'Beneficiary marked as released successfully');
    }

    public function sendSms($id)
    {
        $beneficiary = Beneficiary::findOrFail($id);

        $applicationController = new ApplicationController();

        $message = "Your request has been approved. Please proceed to the MSWD office for further instructions.\n\n-FROM MSWD ABUYOG.\n" . now()->format('F d, Y');
        $applicationController->sendSMSNotification($beneficiary->phone, $message);

        $beneficiary->increment('message_count');

        return back()->with('success', "SMS sent Successfully to number {$beneficiary->phone}");
    }

    public function addNewAssistance(Request $request)
    {
        $request->validate([
            'name_of_assistance' => ['required'],
            'type_of_assistance' => ['required'],
            'amount' => ['nullable', 'min:0']
        ]);

        Assistance::create([
            'name_of_assistance' => $request->name_of_assistance,
            'type_of_assistance' => $request->type_of_assistance,
            'amount' => $request->amount,
        ]);

        return back()->with('success', 'Assistance added successfully');
    }

    private function getRequirements($application)
    {
        $requirements = [];

        if (!$application->service) {
            return ['No specific requirements'];
        }

        if ($application->service->id == 4) { // AICS service
            $aicsType = $application->aicsDetails[0]->type_of_assistance ?? 'Default';


            switch ($aicsType) {
                case 'Medical Assistance':
                    $requirements = [
                        'Barangay Certification/Certificate of Indigency - 1 original and 2 photocopy',
                        'Medical Certification/Clinical Abstract issued within 3 months (with signature and license number of the attending physician - 1 original and 2 photocopy',
                        'Statement of Account/Billing Statement(for Billing) - 1 original and 2 photocopy',
                        'Pharmacy Receipt - 1 original and 2 photocopy',
                        "Doctor's Prescription - 1 original and 2 photocopy",
                        'Fully accomplished Application Form'
                    ];
                    break;
                case 'Burial Assistance':
                    $requirements = [
                        'Barangay Certification/Certificate of Indigency - 1 original and 2 photocopy',
                        'Registered Death Certificate - 1 original and 2 photocopy',
                        'Funeral Contract - 1 original and 2 photocopy',
                        'Senior Citizen Certification (deceased senior citizen) - 1 original and 2 photocopy',
                        "Senior Citizen's Id (deceased senior citizen) - Certified True Copy and 2 photocopy",
                        'Fully accomplished Application Form'
                    ];
                    break;
                case 'Transportation Assistance':
                    $requirements = [
                        'Barangay Certification/Certificate of Indigency - 1 original and 2 photocopy',
                        'Social Case Study Report - 2 Original Copy',
                        'Letter Request - 1 original and 2 photocopy',
                        'Fully accomplished Application Form'
                    ];
                    break;
                case 'Food Assistance':
                    $requirements = [
                        'Intake/Interview of clients suffering from starvation to determine eligibility for assistance',
                        'Fully accomplished Application Form'
                    ];
                    break;
                case 'Emergency Shelter Assistance':
                    $requirements = [
                        'Fully accomplished Application Form',
                        'Bureau of Fire Protection Certification',
                        "Intake/Interview of client to determine one's eligibility for assistance",
                        'Picture of the damaged house - 3 copies',
                        'Fully accomplished Application Form'
                    ];
                    break;
                case 'Educational Assistance':
                    $requirements = [
                        'Any valid ID/Barangay Certificate/ Certificate of Indigency - 3 photocopies',
                        'School ID of student/beneficiary - 3 photocopies',
                        'Certificate of Enrollment or Registration - 1 original and 2 photocopy',
                        'Assessment Form/Statement of Account - 1 original and 2 photocopy',
                        'Social Case Study Report (SCSR) from the MSWDO',
                        'Fully accomplished Application Form'
                    ];
                    break;
                default:
                    $requirements = ['No specific requirements'];
                    break;
            }
        } else {
            // Other services' requirements
            switch ($application->service->id) {
                case 1:
                    $requirements = ['Valid ID', 'Accomplished Certification and Authorization', 'Certificate of Existence', 'Fully accomplished Application Form'];
                    break;
                case 2:
                    $requirements = ['Updated Barangay Certificate', '1x1 ID Picture', 'Birth Certificate / Voter\'s Certification', 'Medical Certificate', 'Whole Body picture of PWD applicant', 'Fully accomplished Application Form'];
                    break;
                case 3:
                    $requirements = ['Certificate of No Marriage (CENOMAR)', '2×2 Photo', 'Fully accomplished Application Form', 'PSA Birth Certificate/s', 'Spouse’s Death Certificate', 'Certificate of Annulment/Nullity of Marriage', 'Income Tax Return (ITR) or Document Showing Income Level', 'Barangay Certificate', 'Proof of Financial Status', 'Supporting Documents/Certificates'];
                    break;
                default:
                    $requirements = ['No specific requirements'];
                    break;
            }
        }

        return collect($requirements);
    }

    public function submitAssistance(Request $request, $id)
    {
        $assistance = Assistance::findOrFail($id);
        $idsData = $request->beneficiary_ids_data;
        $excludedIdsData = $request->excluded_beneficiary_ids_data;


        $mergedData = $request->include_excluded === "Yes" ? array_merge(explode(',', $idsData[0]), explode(',', $excludedIdsData[0])) : explode(',', $idsData[0]);
        $beneficiaries = Beneficiary::whereIn('id', $mergedData)
            ->get()
            ->reject(function ($query) {
                return $query->expiredBeneficiary();
            });


        foreach ($beneficiaries as $beneficiary) {
            $beneficiaryCreated = BenefitReceived::create([
                'beneficiary_id' => $beneficiary->id,
                'assistance_id' => $assistance->id,
                'status' => 'Pending',
            ]);

            $requirements = $this->getRequirements($beneficiary);

            $sms = new ApplicationController();

            $message = "Good day, {$beneficiary->full_name}! You are eligible for the {$beneficiaryCreated->assistance->name_of_assistance}. Kindly visit MSWD OFFICE of Abuyog to claim/avail the assistance. Please bring:\n{$requirements->map(fn($requirement) => '- ' .$requirement)->implode("\n")}. For inquiries, contact us at 09367639686. Thank you!";

            $sms->sendSMSNotification($beneficiary->phone, $message);

            $beneficiary->increment('message_count');
        }

        if ($beneficiaries->count() <= 0) {
            return back()->with('error', 'No beneficiaries selected.');
        }

        $assistance->update([
            'date_received' => now()

        ]);

        return back()->with('success', 'Assistance submitted successfully');
    }

    public function displayAllLogs()
    {
        $logs = Log::query()->with(['user', 'beneficiary'])->get([
            'user_id',
            'beneficiary_id',
            'log_entry',
            'created_at'
        ]);

        return view('admin.logs', compact('logs'));
    }
}
