<?php

declare(strict_types=1);

/*
 | Map these values to the existing database. Identifiers are validated before use.
 | Keep writes_enabled=false until the mapping has been verified on a backup database.
 */
return [
    'table' => env('CUSTOMER_TABLE', 'customers'),
    'tracking_table' => 'customer_installations',
    // The company whose table shows and edits the data in tracking_table (the imported CAP+ data). Every other company
    // keeps its own data in company_installations. Matched by name since ids differ per server; when no company has
    // this name, the default company (companies.is_default) is used.
    'shared_data_company' => env('SHARED_DATA_COMPANY', 'CAP+'),
    // Database holding the Landy Home worksheets/employee tables, on the same MySQL server as cap_plus.
    // The "วิศวกร" column defaults to employee.initial_name of the worksheet's employee_id from here.
    'landyhome_database' => env('LANDYHOME_DATABASE', 'landyhome'),
    // false: customers are added in this system (the "เพิ่มลูกค้า" button shows); the view no longer reads Landy Home.
    'master_managed_externally' => false,
    'writes_enabled' => filter_var(env('CUSTOMER_WRITES_ENABLED', false), FILTER_VALIDATE_BOOL),
    'columns' => [
        'id' => 'id',
        'name' => 'customer_name',
        'zone' => 'zone',
        'machine_size' => 'machine_size',
        'po_sent_date' => 'po_sent_date',
        'po_due_date' => 'po_due_date',
        'engineer_appointment_1' => 'engineer_appointment_1',
        'installation_date_1' => 'installation_date_1',
        'engineer_appointment_2' => 'engineer_appointment_2',
        'installation_date_2' => 'installation_date_2',
        'engineer_appointment_3' => 'engineer_appointment_3',
        'installation_date_3' => 'installation_date_3',
        'due_60_date' => 'due_60_date',
        'status' => 'status',
        'notes' => 'notes',
        'construction_notes' => 'construction_notes',
        'work_status' => 'work_status',
        'cap_notes' => 'cap_notes',
        'updated_by' => 'updated_by',
        'updated_at' => 'updated_at',
        'contract_sign_date' => 'contract_sign_date',
    ],
    'status' => [
        'completed' => ['YES', 'COMPLETED', 'DONE', 'เสร็จแล้ว'],
        'pending' => ['NO', 'PENDING', 'WAITING', 'รอดำเนินการ'],
    ],
    'near_due_days' => 7,
    'machine_sizes' => [
        'SP', 'M', 'M+M', 'S', 'SP+SP', 'S+SP', 'S+S', 'M+SP',
        'CAP200', 'M-S-CAP200', 'CAP Air', 'cap air + cap200',
    ],
    'zones' => ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10'],
    'status_options' => ['YES', 'NO', 'รอ', 'STATUS'],
    'editable_fields_by_role' => [
        'staff' => [
            'installation_date_1',
            'installation_date_2',
            'installation_date_3',
            'status',
            'work_status',
            'construction_notes',
            'cap_notes',
        ],
    ],
];
