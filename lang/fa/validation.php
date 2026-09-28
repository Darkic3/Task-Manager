<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'accepted' => ':attribute باید پذیرفته شود.',
    'accepted_if' => 'هنگامی که :other برابر :value است، :attribute باید پذیرفته شود.',
    'active_url' => ':attribute یک آدرس معتبر نیست.',
    'after' => ':attribute باید تاریخی بعد از :date باشد.',
    'after_or_equal' => ':attribute باید تاریخی مساوی یا بعد از :date باشد.',
    'alpha' => ':attribute فقط باید شامل حروف باشد.',
    'alpha_dash' => ':attribute فقط باید شامل حروف، اعداد، خط فاصله و زیرخط باشد.',
    'alpha_num' => ':attribute فقط باید شامل حروف و اعداد باشد.',
    'array' => ':attribute باید یک آرایه باشد.',
    'before' => ':attribute باید تاریخی قبل از :date باشد.',
    'before_or_equal' => ':attribute باید تاریخی مساوی یا قبل از :date باشد.',
    'between' => [
        'array' => ':attribute باید بین :min و :max آیتم داشته باشد.',
        'file' => ':attribute باید بین :min و :max کیلوبایت باشد.',
        'numeric' => ':attribute باید بین :min و :max باشد.',
        'string' => ':attribute باید بین :min و :max کاراکتر باشد.',
    ],
    'boolean' => 'فیلد :attribute فقط می‌تواند صحیح یا غلط باشد.',
    'confirmed' => 'تکرار :attribute مطابقت ندارد.',
    'current_password' => 'رمز عبور فعلی نادرست است.',
    'date' => ':attribute یک تاریخ معتبر نیست.',
    'date_equals' => ':attribute باید تاریخی برابر با :date باشد.',
    'date_format' => ':attribute با الگوی :format مطابقت ندارد.',
    'different' => ':attribute و :other باید متفاوت باشند.',
    'digits' => ':attribute باید :digits رقم باشد.',
    'digits_between' => ':attribute باید بین :min و :max رقم باشد.',
    'email' => ':attribute باید یک آدرس ایمیل معتبر باشد.',
    'ends_with' => ':attribute باید با یکی از موارد زیر خاتمه یابد: :values.',
    'exists' => ':attribute انتخاب شده نامعتبر است.',
    'file' => ':attribute باید یک فایل باشد.',
    'filled' => 'فیلد :attribute الزامی است.',
    'image' => ':attribute باید یک تصویر باشد.',
    'in' => ':attribute انتخاب شده نامعتبر است.',
    'in_array' => 'فیلد :attribute در :other وجود ندارد.',
    'integer' => ':attribute باید یک عدد صحیح باشد.',
    'ip' => ':attribute باید یک آدرس IP معتبر باشد.',
    'ipv4' => ':attribute باید یک آدرس IPv4 معتبر باشد.',
    'ipv6' => ':attribute باید یک آدرس IPv6 معتبر باشد.',
    'json' => ':attribute باید یک رشته JSON معتبر باشد.',
    'max' => [
        'array' => ':attribute نباید بیش از :max آیتم داشته باشد.',
        'file' => ':attribute نباید بزرگتر از :max کیلوبایت باشد.',
        'numeric' => ':attribute نباید بزرگتر از :max باشد.',
        'string' => ':attribute نباید بیش از :max کاراکتر باشد.',
    ],
    'mimes' => ':attribute باید فایلی از نوع: :values باشد.',
    'mimetypes' => ':attribute باید فایلی از نوع: :values باشد.',
    'min' => [
        'array' => ':attribute باید حداقل :min آیتم داشته باشد.',
        'file' => ':attribute باید حداقل :min کیلوبایت باشد.',
        'numeric' => ':attribute باید حداقل :min باشد.',
        'string' => ':attribute باید حداقل :min کاراکتر باشد.',
    ],
    'not_in' => ':attribute انتخاب شده نامعتبر است.',
    'numeric' => ':attribute باید یک عدد باشد.',
    'password' => [
        'letters' => ':attribute باید حداقل شامل یک حرف باشد.',
        'mixed' => ':attribute باید حداقل شامل یک حرف بزرگ و یک حرف کوچک باشد.',
        'numbers' => ':attribute باید حداقل شامل یک عدد باشد.',
        'symbols' => ':attribute باید حداقل شامل یک نماد باشد.',
        'uncompromised' => ':attribute وارد شده در درز اطلاعاتی یافت شده است. لطفاً یک :attribute دیگر انتخاب کنید.',
    ],
    'present' => 'فیلد :attribute باید وجود داشته باشد.',
    'prohibited' => 'فیلد :attribute ممنوع است.',
    'regex' => 'فرمت :attribute نامعتبر است.',
    'required' => 'تکمیل فیلد :attribute الزامی است.',
    'required_if' => 'هنگامی که :other برابر :value است، فیلد :attribute الزامی است.',
    'required_unless' => 'فیلد :attribute الزامی است مگر اینکه :other در :values باشد.',
    'required_with' => 'هنگامی که :values وجود دارد، فیلد :attribute الزامی است.',
    'required_with_all' => 'هنگامی که :values وجود دارند، فیلد :attribute الزامی است.',
    'required_without' => 'هنگامی که :values وجود ندارد، فیلد :attribute الزامی است.',
    'required_without_all' => 'هنگامی که هیچ یک از :values وجود ندارند، فیلد :attribute الزامی است.',
    'same' => ':attribute و :other باید یکسان باشند.',
    'size' => [
        'array' => ':attribute باید شامل :size آیتم باشد.',
        'file' => ':attribute باید :size کیلوبایت باشد.',
        'numeric' => ':attribute باید :size باشد.',
        'string' => ':attribute باید :size کاراکتر باشد.',
    ],
    'string' => ':attribute باید یک رشته متنی باشد.',
    'timezone' => ':attribute باید یک منطقه زمانی معتبر باشد.',
    'unique' => ':attribute قبلاً انتخاب شده است.',
    'url' => ':attribute باید یک آدرس اینترنتی معتبر باشد.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'name' => 'نام',
        'title' => 'عنوان',
        'description' => 'توضیحات',
        'email' => 'ایمیل',
        'password' => 'رمز عبور',
        'current_password' => 'رمز عبور فعلی',
        'password_confirmation' => 'تکرار رمز عبور',
        'status' => 'وضعیت',
        'priority' => 'اولویت',
        'due_date' => 'تاریخ سررسید',
        'start_date' => 'تاریخ شروع',
        'project_id' => 'پروژه',
        'phone' => 'شماره تماس',
        'location' => 'موقعیت مکانی',
        'website' => 'وب‌سایت',
        'bio' => 'بیوگرافی',
        'avatar' => 'تصویر پروفایل',
        'frequency' => 'تکرار',
        'locale' => 'زبان',
    ],

];
