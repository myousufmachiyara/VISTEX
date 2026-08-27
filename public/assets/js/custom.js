/* custom.js — MH Fabrics ERP
   ─────────────────────────────────────────────────────────────────────
   Rules:
   • No duplicate event listeners — changePasswordForm is handled
     exclusively in app.blade.php (inline script after footer include).
     Do NOT add another listener here.
   • All jQuery-dependent code runs after DOM ready or is wrapped in
     $(document).ready() / window load events.
   ─────────────────────────────────────────────────────────────────────
*/

// ── Page cache reload (back/forward cache fix) ────────────────────────
window.addEventListener('pageshow', function (event) {
    if (event.persisted) {
        window.location.reload();
    }
});

// ── Hide loader once page is fully loaded ────────────────────────────
// Also handled in app.blade.php via window 'load' event.
// Keeping both is safe — whichever fires first will hide the loader.
$(window).on('load', function () {
    $('#loader').addClass('hidden');
});

// ── Allow Enter key inside textareas (prevent form-wide suppression) ──
// Some blades use onkeydown="return event.key != 'Enter'" on the <form>
// which blocks Enter everywhere including textareas. This restores it
// for any element with class .cust-textarea.
document.querySelectorAll('.cust-textarea').forEach(function (textarea) {
    textarea.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.stopPropagation();
        }
    });
});

// ── Currency to words converter ───────────────────────────────────────
// Used in invoice print views: convertCurrencyToWords(amount)
function convertCurrencyToWords(number) {
    var Thousand = 1000;
    var Million  = Thousand * Thousand;
    var Billion  = Thousand * Million;
    var Trillion = Thousand * Billion;

    if (number === 0) return 'Zero Rupees Only';

    var isNegative = number < 0;
    number = Math.abs(number);

    var result = '';

    if (number >= Trillion) {
        result += convertDigitGroup(Math.floor(number / Trillion)) + ' Trillion ';
        number %= Trillion;
    }
    if (number >= Billion) {
        result += convertDigitGroup(Math.floor(number / Billion)) + ' Billion ';
        number %= Billion;
    }
    if (number >= Million) {
        result += convertDigitGroup(Math.floor(number / Million)) + ' Million ';
        number %= Million;
    }
    if (number >= Thousand) {
        result += convertDigitGroup(Math.floor(number / Thousand)) + ' Thousand ';
        number %= Thousand;
    }
    if (number > 0) {
        result += convertDigitGroup(number);
    }

    result = result.trim() + ' Rupees Only';
    return isNegative ? 'Negative ' + result : result;
}

function convertDigitGroup(number) {
    // Single digit shortcut
    var singles = ['', 'One', 'Two', 'Three', 'Four', 'Five',
                   'Six', 'Seven', 'Eight', 'Nine'];
    if (number >= 1 && number <= 9) return singles[number];

    var hundreds  = Math.floor(number / 100);
    var remainder = number % 100;
    var result    = '';

    if (hundreds > 0) {
        result += singles[hundreds] + ' Hundred ';
    }
    if (remainder > 0) {
        if (remainder < 20) {
            result += convertTens(remainder);
        } else {
            result += convertTens(Math.floor(remainder / 10) * 10);
            if (remainder % 10 > 0) {
                result += '-' + singles[remainder % 10];
            }
        }
    }

    return result.trim();
}

function convertTens(number) {
    var tens = {
        10: 'Ten',    11: 'Eleven',    12: 'Twelve',   13: 'Thirteen',
        14: 'Fourteen', 15: 'Fifteen', 16: 'Sixteen',  17: 'Seventeen',
        18: 'Eighteen', 19: 'Nineteen',
        20: 'Twenty', 30: 'Thirty',   40: 'Forty',     50: 'Fifty',
        60: 'Sixty',  70: 'Seventy',  80: 'Eighty',    90: 'Ninety'
    };
    return tens[number] || '';
}

// ── Session timeout ───────────────────────────────────────────────────
// Warning at 28 min, logout at 30 min of inactivity.
var timeoutWarning  = 28 * 60 * 1000;
var timeoutRedirect = 30 * 60 * 1000;
var warningTimeout;
var redirectTimeout;

function resetTimer() {
    clearTimeout(warningTimeout);
    clearTimeout(redirectTimeout);
    warningTimeout  = setTimeout(showSessionModal,  timeoutWarning);
    redirectTimeout = setTimeout(expireSession,     timeoutRedirect);
}

function showSessionModal() {
    var modal = document.getElementById('timeoutModal');
    if (modal) modal.style.display = 'block';
}

function expireSession() {
    fetch('/logout', {
        method:  'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(function (response) {
        if (response.ok) {
            window.location.href = '/login';
        }
    })
    .catch(function (err) {
        console.error('Session timeout logout failed:', err);
        window.location.href = '/login'; // redirect anyway
    });
}

// Keep-alive button inside timeout modal
$(document).on('click', '#continueSession', function () {
    fetch('/keep-alive', {
        method:  'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(function () {
        var modal = document.getElementById('timeoutModal');
        if (modal) modal.style.display = 'none';
        resetTimer();
    })
    .catch(function (err) {
        console.error('Keep-alive failed:', err);
    });
});

// Manual logout from timeout modal
$(document).on('click', '#logoutSession', function () {
    expireSession();
});

// Reset timer on any user activity
$(document).on('mousemove keypress click scroll', resetTimer);

// Start timer on page load
resetTimer();

// ── FIX: REMOVED the duplicate changePasswordForm submit listener ─────
//
// The original custom.js had:
//   document.getElementById('changePasswordForm').addEventListener('submit', ...)
//
// This conflicted with the correct implementation in app.blade.php.
// The conflict caused:
//   1. Two listeners firing on every submit attempt
//   2. The old listener called a /validate-user-password/ route that
//      doesn't exist in the new project, causing AJAX errors
//   3. Race condition between the two listeners produced unpredictable
//      behavior (sometimes double-submit, sometimes silent failure)
//
// The single authoritative change-password handler lives in app.blade.php.
// Do NOT add another one here.