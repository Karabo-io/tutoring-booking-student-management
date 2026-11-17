document.addEventListener('DOMContentLoaded', function () {
    var dataElement = document.getElementById('bookingData');
    var tbody = document.querySelector('#bookingTable tbody');
    if (!dataElement || !tbody) {
        return;
    }

    var bookings = [];
    try {
        bookings = JSON.parse(dataElement.textContent);
    } catch (err) {
        bookings = [];
    }

    var filter = document.getElementById('statusFilter');
    var summary = document.getElementById('bookingSummary');

    function countByStatus(list) {
        var counts = { Requested: 0, Confirmed: 0, Completed: 0, Cancelled: 0 };
        list.forEach(function (b) {
            if (Object.prototype.hasOwnProperty.call(counts, b.status)) {
                counts[b.status] += 1;
            }
        });
        return counts;
    }

    function render() {
        var chosen = filter ? filter.value : 'All';

        var shown = bookings.filter(function (b) {
            return chosen === 'All' || b.status === chosen;
        });
        shown.sort(function (a, b) {
            return (a.date + a.time).localeCompare(b.date + b.time);
        });

        tbody.textContent = '';

        if (shown.length === 0) {
            var emptyRow = document.createElement('tr');
            var emptyCell = document.createElement('td');
            emptyCell.colSpan = 6;
            emptyCell.textContent = 'No sessions to show.';
            emptyRow.appendChild(emptyCell);
            tbody.appendChild(emptyRow);
        }

        shown.forEach(function (b) {
            var row = document.createElement('tr');
            [b.date, b.day, b.time, b.programme, b.person].forEach(function (text) {
                var cell = document.createElement('td');
                cell.textContent = text;                 // textContent keeps this XSS safe
                row.appendChild(cell);
            });
            var statusCell = document.createElement('td');
            var badge = document.createElement('span');
            badge.className = 'badge ' + String(b.status).toLowerCase();
            badge.textContent = b.status;
            statusCell.appendChild(badge);
            row.appendChild(statusCell);
            tbody.appendChild(row);
        });

        if (summary) {
            var c = countByStatus(bookings);
            summary.textContent = c.Confirmed + ' confirmed, ' + c.Completed + ' completed, ' +
                c.Cancelled + ' cancelled';
        }
    }

    if (filter) {
        filter.addEventListener('change', render);
    }
    render();
});