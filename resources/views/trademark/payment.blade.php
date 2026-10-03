@extends('layouts.app')

@section('content')
    <div class="container mt-5">
        <div class="row">
            <!-- Left: Payment Details -->
            <div class="col-md-7">
                <div class="card shadow mb-4">
                    <div class="card-header bg-primary text-white">
                        <h3>Payment Information</h3>
                        <small>Step 2: 50% Advance Payment</small>
                    </div>
                    <div class="card-body p-5">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-circle"></i>
                            <strong>Important:</strong> 50% advance payment is required before document submission.
                        </div>

                        <form action="{{ route('payment.show', $application->id) }}" method="GET" id="paymentForm">
                            @csrf

                            <!-- Trademark Details Summary -->
                            <h5 class="mb-3">Trademark Details:</h5>
                            <div class="table-responsive mb-4">
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>Trademark:</strong></td>
                                        <td>{{ $application->brand_name }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Type:</strong></td>
                                        <td>{{ $application->entity_type === 'individual' ? 'Individual / Proprietor / Trader' : ucfirst($application->entity_type) }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Industry:</strong></td>
                                        <td>{{ $application->industry }}</td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Payment Breakdown -->
                            <h5 class="mb-3">Payment Breakdown:</h5>
                            <div class="card bg-light mb-4">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Total Professional Fee:</span>
                                        <span>£{{ number_format($totalAmount, 0) }}</span>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between text-success mb-2">
                                        <span><strong>50% Advance Payment:</strong></span>
                                        <span class="h5">£{{ number_format($amount, 0) }}</span>
                                    </div>
                                    <small class="text-muted">Remaining £{{ number_format($totalAmount - $amount, 0) }} to
                                        be paid after filing</small>
                                </div>
                            </div>

                            <!-- Payment Method -->
                            <div class="mb-3">
                                <label class="form-label"><strong>Payment Method</strong></label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" value="razorpay"
                                        id="razorpay" checked required>
                                    <label class="form-check-label" for="razorpay">
                                        Razorpay (UPI, Cards, Net Banking)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" value="stripe"
                                        id="stripe" required>
                                    <label class="form-check-label" for="stripe">
                                        Stripe (Credit Card)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" value="upi"
                                        id="upi" required>
                                    <label class="form-check-label" for="upi">
                                        Direct UPI Transfer
                                    </label>
                                </div>
                            </div>

                            <!-- Terms -->
                            @include('partials.government-fee-notice')
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the payment terms and conditions
                                </label>
                            </div>

                            <button type="submit" class="btn btn-success w-100 btn-lg">
                                <i class="fas fa-lock"></i> Proceed to Payment - £{{ number_format($amount, 0) }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right: Required Documents Info -->
            <div class="col-md-5">
                <div class="card shadow">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Documents Required</h5>
                    </div>
                    <div class="card-body">
                        @if ($application->entity_type === 'individual')
                            <div class="list-group list-group-flush">
                                <div class="list-group-item">
                                    <span class="badge bg-primary">1</span> Passport or Driving Licence
                                </div>
                                <div class="list-group-item">
                                    <span class="badge bg-primary">2</span> Address Proof
                                </div>
                                <div class="list-group-item">
                                    <span class="badge bg-primary">3</span> Affidavit
                                </div>
                                <div class="list-group-item">
                                    <span class="badge bg-primary">4</span> Power of Attorney
                                </div>
                            </div>
                        @else
                            <div class="list-group list-group-flush">
                                <div class="list-group-item">
                                    <span class="badge bg-primary">1</span> Certificate of Incorporation
                                </div>
                                <div class="list-group-item">
                                    <span class="badge bg-primary">2</span> Registered Office Address Proof
                                </div>
                                <div class="list-group-item">
                                    <span class="badge bg-primary">3</span> Authorized Signatory ID
                                </div>
                                <div class="list-group-item">
                                    <span class="badge bg-primary">4</span> Affidavit
                                </div>
                                <div class="list-group-item">
                                    <span class="badge bg-primary">5</span> Power of Attorney
                                </div>
                            </div>
                        @endif

                        <div class="alert alert-info mt-3">
                            <small>
                                <i class="fas fa-info-circle"></i>
                                You'll upload all documents after completing this payment.
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Timeline -->
                <div class="card shadow mt-3">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">Application Timeline</h5>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <div class="timeline-item">
                                <div class="timeline-marker completed">
                                    <i class="fas fa-check"></i>
                                </div>
                                <div class="timeline-content">
                                    <h6>Basic Info</h6>
                                    <small class="text-muted">Completed</small>
                                </div>
                            </div>
                            <div class="timeline-item active">
                                <div class="timeline-marker">
                                    <i class="fas fa-credit-card"></i>
                                </div>
                                <div class="timeline-content">
                                    <h6>Payment</h6>
                                    <small class="text-muted">In Progress</small>
                                </div>
                            </div>
                            <div class="timeline-item">
                                <div class="timeline-marker">
                                    <i class="fas fa-file"></i>
                                </div>
                                <div class="timeline-content">
                                    <h6>Documents</h6>
                                    <small class="text-muted">Pending</small>
                                </div>
                            </div>
                            <div class="timeline-item">
                                <div class="timeline-marker">
                                    <i class="fas fa-check-double"></i>
                                </div>
                                <div class="timeline-content">
                                    <h6>Admin Approval</h6>
                                    <small class="text-muted">Pending</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .timeline {
            position: relative;
        }

        .timeline-item {
            display: flex;
            margin-bottom: 20px;
            position: relative;
        }

        .timeline-item:not(:last-child)::after {
            content: '';
            position: absolute;
            left: 15px;
            top: 40px;
            height: 50px;
            width: 2px;
            background: #dee2e6;
        }

        .timeline-marker {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #dee2e6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: white;
            flex-shrink: 0;
        }

        .timeline-marker.completed {
            background: #28a745;
        }

        .timeline-marker.active {
            background: #007bff;
        }

        .timeline-content {
            margin-left: 15px;
        }
    </style>
@endsection
