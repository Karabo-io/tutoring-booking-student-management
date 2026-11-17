function showErrors(form, errors) {
    var box = form.querySelector('.js-errors');
    if (!box) {
        box = document.createElement('ul');
        box.className = 'js-errors alert error';
        form.insertBefore(box, form.firstChild);
    }
    box.textContent = '';
    errors.forEach(function (msg) {
        var li = document.createElement('li');
        li.textContent = msg;        
        box.appendChild(li);
    });
    box.style.display = errors.length > 0 ? 'block' : 'none';
}

function attachValidation(formId, checker) {
    var form = document.getElementById(formId);
    if (!form) {
        return;
    }
    form.addEventListener('submit', function (event) {
        var errors = checker(form);
        showErrors(form, errors);
        if (errors.length > 0) {
            event.preventDefault();
            window.scrollTo(0, 0);
        }
    });
}

function isStrongPassword(pw) {
    return pw.length >= 8 && /[A-Z]/.test(pw) && /[a-z]/.test(pw) && /[0-9]/.test(pw);
}

function todayString() {
    var d = new Date();
    var month = String(d.getMonth() + 1).padStart(2, '0');
    var day = String(d.getDate()).padStart(2, '0');
    return d.getFullYear() + '-' + month + '-' + day;
}

function checkRegister(form) {
    var errors = [];
    if (form.first_name.value.trim().length < 2) {
        errors.push('Enter your first name.');
    }
    if (form.last_name.value.trim().length < 2) {
        errors.push('Enter your last name.');
    }
    if (!/^0[0-9]{9}$/.test(form.phone.value.trim())) {
        errors.push('Phone number must be 10 digits and start with 0.');
    }
    if (!isStrongPassword(form.password.value)) {
        errors.push('Password must be at least 8 characters with an uppercase letter, a lowercase letter and a number.');
    }
    if (form.password.value !== form.confirm_password.value) {
        errors.push('The two passwords do not match.');
    }
    return errors;
}

function checkLogin(form) {
    var errors = [];
    if (form.elements['role'].value === '') {
        errors.push('Select whether you are a student, tutor or administrator.');
    }
    if (form.email.value.trim() === '') {
        errors.push('Enter your email address.');
    }
    if (form.password.value === '') {
        errors.push('Enter your password.');
    }
    return errors;
}

function checkPayment(form) {
    var errors = [];
    if (form.programme_id.value === '') {
        errors.push('Please select a programme.');
    }
    if (form.card_name.value.trim().length < 2) {
        errors.push('Enter the name on the card.');
    }
    if (!/^[0-9]{16}$/.test(form.card_number.value)) {
        errors.push('Card number must be exactly 16 digits.');
    }
    if (!/^[0-9]{3}$/.test(form.cvv.value)) {
        errors.push('CVV must be 3 digits.');
    }
    var match = /^(0[1-9]|1[0-2])\/([0-9]{2})$/.exec(form.expiry.value);
    if (!match) {
        errors.push('Expiry date must be in the format MM/YY.');
    } else {
        var now = new Date();
        var nowValue = now.getFullYear() * 12 + now.getMonth();               
        var expValue = (2000 + parseInt(match[2], 10)) * 12 + (parseInt(match[1], 10) - 1);
        if (expValue < nowValue) {
            errors.push('This card has expired.');
        }
    }
    return errors;
}

function checkBooking(form) {
    var errors = [];
    if (form.enrolment_id.value === '') {
        errors.push('Please choose a programme.');
    }
    var dateValue = form.session_date.value;
    if (dateValue === '') {
        errors.push('Please choose a date.');
    } else {
        var day = new Date(dateValue + 'T00:00:00').getDay();      // 0 = Sunday, 6 = Saturday
        if (day === 0 || day === 6) {
            errors.push('Sessions can only be booked from Monday to Friday.');
        }
        if (dateValue < todayString()) {
            errors.push('You cannot book a session in the past.');
        }
    }
    if (form.start_time.value === '') {
        errors.push('Please choose a start time.');
    }
    return errors;
}

function checkAvailability(form) {
    var errors = [];
    if (form.day_of_week.value === '') {
        errors.push('Choose a day.');
    }
    if (form.start_time.value === '' || form.end_time.value === '') {
        errors.push('Enter a start and an end time.');
    } else if (form.end_time.value <= form.start_time.value) {
        errors.push('The end time must be after the start time.');
    }
    return errors;
}

function checkProgramme(form) {
    var errors = [];
    var fee = parseFloat(form.fee.value);
    if (isNaN(fee) || fee <= 0) {
        errors.push('The fee must be more than 0.');
    }
    return errors;
}

function setupAmountDue() {
    var select = document.getElementById('programme_id');
    var output = document.getElementById('amountDue');
    if (!select || !output) {
        return;
    }
    select.addEventListener('change', function () {
        var fee = parseFloat(select.options[select.selectedIndex].getAttribute('data-fee'));
        output.textContent = isNaN(fee) ? 'R 0.00' : 'R ' + fee.toFixed(2);
    });
}

var PAGE_MESSAGES = {
    registered:     { type: 'success', text: 'Registration successful. You can now log in.' },
    logged_out:     { type: 'success', text: 'You have been logged out.' },
    invalid_login:  { type: 'error', text: 'Incorrect email, password or role. Please try again.' },
    login_required: { type: 'error', text: 'Please log in to continue.' },
    timeout:        { type: 'error', text: 'You were logged out because of inactivity. Please log in again.' },
    locked:         { type: 'error', text: 'Too many failed attempts. Please wait 5 minutes and try again.' },
    email_taken:    { type: 'error', text: 'That email address is already registered.' }
};

function showPageMessage() {
    var holder = document.getElementById('pageMessage');
    if (!holder) {
        return;
    }
    var code = new URLSearchParams(window.location.search).get('msg');
    if (code && Object.prototype.hasOwnProperty.call(PAGE_MESSAGES, code)) {
        holder.textContent = PAGE_MESSAGES[code].text;
        holder.className = 'alert ' + PAGE_MESSAGES[code].type;
        holder.style.display = 'block';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    attachValidation('registerForm', checkRegister);
    attachValidation('loginForm', checkLogin);
    attachValidation('paymentForm', checkPayment);
    attachValidation('bookingForm', checkBooking);
    attachValidation('availabilityForm', checkAvailability);
    attachValidation('programmeForm', checkProgramme);
    setupAmountDue();
    showPageMessage();
});