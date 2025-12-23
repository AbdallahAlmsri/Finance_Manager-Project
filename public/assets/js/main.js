window.addEventListener("DOMContentLoaded", function () {

    var themeToggle = document.getElementById("themeToggle");

    if (themeToggle) {
        function applyTheme(theme) {
            if (theme === "dark") {
                document.body.classList.add("dark-mode");
                themeToggle.textContent = "Light Mode";
            } else {
                document.body.classList.remove("dark-mode");
                themeToggle.textContent = "Dark Mode";
            }
            localStorage.setItem("theme", theme);
        }

        var savedTheme = localStorage.getItem("theme") || "light";
        applyTheme(savedTheme);

        themeToggle.addEventListener("click", function(e) {
            e.preventDefault();
            e.stopPropagation();
            var nextTheme = document.body.classList.contains("dark-mode") ? "light" : "dark";
            applyTheme(nextTheme);
            if (window.redrawCharts) {
                window.redrawCharts();
            }
        });
    }


    var txForm = document.getElementById("transactionForm");
    var txTableBody = document.querySelector("#transactionsTable tbody");

    if (txForm && txTableBody) {
        txForm.addEventListener("submit", function (e) {
            e.preventDefault();

            var type = document.getElementById("txType").value;
            var category = document.getElementById("txCategory").value;
            var amount = document.getElementById("txAmount").value;
            var date = document.getElementById("txDate").value;
            var note = document.getElementById("txNote").value || '';

            if (!type || !category || !amount || !date) {
                alert("Please fill in all required fields.");
                return;
            }

            var typeLower = type.toLowerCase();
            var sign = type === 'Income' ? '+' : '-';
            var amountClass = 'amount-' + typeLower;

            var tr = document.createElement("tr");
            tr.setAttribute('data-type', typeLower);
            tr.setAttribute('data-date', date);
            tr.setAttribute('data-category', category);
            tr.setAttribute('data-amount', amount);
            tr.setAttribute('data-note', note);

            var notePreview = note.length > 30 ? note.substring(0, 30) + '...' : note;
            var noteTooltip = note.length > 30 ? '<div class="note-tooltip"><div class="note-tooltip-content">' + note + '</div></div>' : '';

            tr.innerHTML =
                '<td><input type="checkbox" class="transaction-checkbox" value="' + Date.now() + '"></td>' +
                "<td>" + date + "</td>" +
                '<td><span class="type-badge type-' + typeLower + '">' + type + '</span></td>' +
                '<td><span class="category-tag">' + category + '</span></td>' +
                '<td class="align-right amount-cell"><span class="' + amountClass + '">' + sign + '$' + Number(amount).toFixed(2) + '</span></td>' +
                '<td class="note-cell"><span class="note-preview">' + notePreview + '</span>' + noteTooltip + '</td>' +
                '<td><button type="button" class="btn-duplicate" title="Duplicate this transaction" data-index="' + Date.now() + '">Copy</button></td>';

            txTableBody.prepend(tr);

            var newCheckbox = tr.querySelector('.transaction-checkbox');
            if (newCheckbox) {
                newCheckbox.addEventListener('change', function() {
                    if (window.updateBulkActions) window.updateBulkActions();
                });
            }

            var duplicateBtn = tr.querySelector('.btn-duplicate');
            if (duplicateBtn) {
                duplicateBtn.addEventListener('click', function() {
                    document.getElementById('txType').value = type;
                    document.getElementById('txCategory').value = category;
                    document.getElementById('txAmount').value = amount;
                    document.getElementById('txDate').value = new Date().toISOString().split('T')[0];
                    document.getElementById('txNote').value = note;
                    document.getElementById('transactionForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            }

            var noteCell = tr.querySelector('.note-cell');
            if (noteCell) {
                var tooltip = noteCell.querySelector('.note-tooltip');
                if (tooltip) {
                    noteCell.addEventListener('mouseenter', function() {
                        tooltip.style.display = 'block';
                    });
                    noteCell.addEventListener('mouseleave', function() {
                        tooltip.style.display = 'none';
                    });
                }
            }
            txForm.reset();
            filterTransactions();
        });
    }

    var searchInput = document.getElementById("searchTransactions");
    var filterSelect = document.getElementById("filterType");
    var allTableRows = [];

    function filterTransactions() {
        var searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
        var filterType = filterSelect ? filterSelect.value.toLowerCase() : '';
        var rows = document.querySelectorAll("#transactionsTable tbody tr");

        rows.forEach(function(row) {
            var rowType = row.getAttribute('data-type') || '';
            var rowText = row.textContent.toLowerCase();

            var matchesSearch = !searchTerm || rowText.includes(searchTerm);
            var matchesFilter = !filterType || rowType === filterType;

            if (matchesSearch && matchesFilter) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    if (searchInput) {
        searchInput.addEventListener("input", filterTransactions);
    }

    if (filterSelect) {
        filterSelect.addEventListener("change", filterTransactions);
    }


    var canvas = document.getElementById("categoryChart");
    if (canvas && canvas.getContext && window.categoryData) {
        var ctx = canvas.getContext("2d");
        var categories = window.categoryData.categories || [];
        var values = window.categoryData.amounts || [];

        if (categories.length === 0) {
            ctx.font = "14px Arial";
            ctx.fillStyle = document.body.classList.contains('dark-mode') ? '#94a3b8' : '#6b7280';
            ctx.textAlign = "center";
            ctx.fillText("No expense data available", canvas.width / 2, canvas.height / 2);
            return;
        }

        var totalSpent = values.reduce(function(a, b) { return a + b; }, 0);
        if (totalSpent === 0) {
            ctx.font = "14px Arial";
            ctx.fillStyle = document.body.classList.contains('dark-mode') ? '#94a3b8' : '#6b7280';
            ctx.textAlign = "center";
            ctx.fillText("No spending data available", canvas.width / 2, canvas.height / 2);
            return;
        }

        var centerX = canvas.width / 2;
        var centerY = canvas.height / 2;
        var radius = Math.min(centerX, centerY) - 50;
        var hoveredIndex = -1;
        var liftAmount = 15;

        var isDark = document.body.classList.contains('dark-mode');
        var colors = [
            '#3b82f6', // Blue
            '#10b981', // Green
            '#f59e0b', // Orange
            '#ef4444', // Red
            '#8b5cf6', // Purple
            '#06b6d4', // Cyan
            '#ec4899', // Pink
            '#84cc16'  // Lime
        ];

        var darkColors = [
            '#60a5fa', // Light Blue
            '#34d399', // Light Green
            '#fbbf24', // Light Orange
            '#f87171', // Light Red
            '#a78bfa', // Light Purple
            '#22d3ee', // Light Cyan
            '#f472b6', // Light Pink
            '#a3e635'  // Light Lime
        ];

        var chartColors = isDark ? darkColors : colors;
        var labelColor = isDark ? '#cbd5e1' : '#6b7280';

        function drawChart() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            var currentAngle = -Math.PI / 2;
            var legendX = canvas.width - 180;
            var legendY = 50;
            var legendSpacing = 25;

            categories.forEach(function(category, i) {
                var sliceAngle = (values[i] / totalSpent) * 2 * Math.PI;
                var color = chartColors[i % chartColors.length];
                var isHovered = (hoveredIndex === i);
                var currentRadius = isHovered ? radius + liftAmount : radius;

                var sliceCenterAngle = currentAngle + sliceAngle / 2;
                var offsetX = isHovered ? Math.cos(sliceCenterAngle) * liftAmount : 0;
                var offsetY = isHovered ? Math.sin(sliceCenterAngle) * liftAmount : 0;

                ctx.beginPath();
                ctx.moveTo(centerX + offsetX, centerY + offsetY);
                ctx.arc(centerX + offsetX, centerY + offsetY, currentRadius, currentAngle, currentAngle + sliceAngle);
                ctx.closePath();
                ctx.fillStyle = color;
                ctx.fill();
                ctx.strokeStyle = isDark ? '#0f172a' : '#ffffff';
                ctx.lineWidth = 2;
                ctx.stroke();

                ctx.fillStyle = color;
                ctx.fillRect(legendX, legendY + i * legendSpacing - 8, 12, 12);
                ctx.fillStyle = labelColor;
                ctx.font = "11px Arial";
                ctx.textAlign = "left";
                ctx.fillText(category, legendX + 18, legendY + i * legendSpacing);

                var percent = ((values[i] / totalSpent) * 100).toFixed(1);
                ctx.fillStyle = labelColor;
                ctx.font = "bold 10px Arial";
                ctx.fillText(percent + "%", legendX + 80, legendY + i * legendSpacing);

                ctx.fillStyle = labelColor;
                ctx.font = "10px Arial";
                ctx.fillText("$" + values[i].toFixed(2), legendX + 120, legendY + i * legendSpacing);

                currentAngle += sliceAngle;
            });
        }

        function getSliceAtPoint(x, y) {
            var dx = x - centerX;
            var dy = y - centerY;
            var distance = Math.sqrt(dx * dx + dy * dy);

            if (distance > radius + liftAmount) {
                return -1;
            }

            var angle = Math.atan2(dy, dx);
            if (angle < -Math.PI / 2) angle += 2 * Math.PI;
            angle += Math.PI / 2; // Adjust for starting at top

            var currentAngle = 0;
            for (var i = 0; i < categories.length; i++) {
                var sliceAngle = (values[i] / totalSpent) * 2 * Math.PI;
                if (angle >= currentAngle && angle <= currentAngle + sliceAngle) {
                    return i;
                }
                currentAngle += sliceAngle;
            }
            return -1;
        }

        canvas.addEventListener('mousemove', function(e) {
            var rect = canvas.getBoundingClientRect();
            var x = e.clientX - rect.left;
            var y = e.clientY - rect.top;
            var newHoveredIndex = getSliceAtPoint(x, y);

            if (newHoveredIndex !== hoveredIndex) {
                hoveredIndex = newHoveredIndex;
                canvas.style.cursor = hoveredIndex >= 0 ? 'pointer' : 'default';
                drawChart();
            }
        });

        canvas.addEventListener('mouseleave', function() {
            hoveredIndex = -1;
            canvas.style.cursor = 'default';
            drawChart();
        });

        drawChart();

        if (!window.redrawCharts) {
            window.redrawCharts = function() {};
        }
        var originalRedraw = window.redrawCharts;
        window.redrawCharts = function() {
            if (originalRedraw) originalRedraw();
            isDark = document.body.classList.contains('dark-mode');
            chartColors = isDark ? darkColors : colors;
            labelColor = isDark ? '#cbd5e1' : '#6b7280';
            drawChart();
        };
    }

    var budgetForm = document.getElementById("budgetForm");
    if (budgetForm) {
        budgetForm.addEventListener("submit", function (e) {
            e.preventDefault();
            budgetForm.reset();
        });
    }

    var budgetCanvas = document.getElementById("budgetChart");
    if (budgetCanvas && budgetCanvas.getContext && window.budgetData) {
        var ctx = budgetCanvas.getContext("2d");
        var categories = window.budgetData.categories || [];
        var limits = window.budgetData.limits || [];
        var spent = window.budgetData.spent || [];

        if (categories.length === 0) {
            ctx.font = "14px Arial";
            ctx.fillStyle = document.body.classList.contains('dark-mode') ? '#94a3b8' : '#6b7280';
            ctx.textAlign = "center";
            ctx.fillText("No budget data available", budgetCanvas.width / 2, budgetCanvas.height / 2);
            return;
        }

        var centerX = budgetCanvas.width * 0.35;
        var centerY = budgetCanvas.height / 2;
        var radius = Math.min(centerX, centerY) - 40;
        var totalSpent = spent.reduce(function(a, b) { return a + b; }, 0);
        var hoveredIndex = -1;
        var liftAmount = 15;

        if (totalSpent === 0) {
            ctx.font = "14px Arial";
            ctx.fillStyle = document.body.classList.contains('dark-mode') ? '#94a3b8' : '#6b7280';
            ctx.textAlign = "center";
            ctx.fillText("No spending data available", budgetCanvas.width / 2, budgetCanvas.height / 2);
            return;
        }

        var isDark = document.body.classList.contains('dark-mode');
        var colors = [
            '#3b82f6', // Blue
            '#10b981', // Green
            '#f59e0b', // Orange
            '#ef4444', // Red
            '#8b5cf6', // Purple
            '#06b6d4', // Cyan
            '#ec4899', // Pink
            '#84cc16'  // Lime
        ];

        var darkColors = [
            '#60a5fa', // Light Blue
            '#34d399', // Light Green
            '#fbbf24', // Light Orange
            '#f87171', // Light Red
            '#a78bfa', // Light Purple
            '#22d3ee', // Light Cyan
            '#f472b6', // Light Pink
            '#a3e635'  // Light Lime
        ];

        var chartColors = isDark ? darkColors : colors;
        var labelColor = isDark ? '#cbd5e1' : '#6b7280';

        function drawChart() {
            ctx.clearRect(0, 0, budgetCanvas.width, budgetCanvas.height);

            var currentAngle = -Math.PI / 2;
            var legendX = budgetCanvas.width * 0.65;
            var legendY = 50;
            var legendSpacing = 25;

            categories.forEach(function(category, i) {
                var sliceAngle = (spent[i] / totalSpent) * 2 * Math.PI;
                var color = chartColors[i % chartColors.length];
                var isHovered = (hoveredIndex === i);
                var currentRadius = isHovered ? radius + liftAmount : radius;

                var sliceCenterAngle = currentAngle + sliceAngle / 2;
                var offsetX = isHovered ? Math.cos(sliceCenterAngle) * liftAmount : 0;
                var offsetY = isHovered ? Math.sin(sliceCenterAngle) * liftAmount : 0;

                ctx.beginPath();
                ctx.moveTo(centerX + offsetX, centerY + offsetY);
                ctx.arc(centerX + offsetX, centerY + offsetY, currentRadius, currentAngle, currentAngle + sliceAngle);
                ctx.closePath();
                ctx.fillStyle = color;
                ctx.fill();
                ctx.strokeStyle = isDark ? '#0f172a' : '#ffffff';
                ctx.lineWidth = 2;
                ctx.stroke();

                ctx.fillStyle = color;
                ctx.fillRect(legendX, legendY + i * legendSpacing - 8, 12, 12);
                ctx.fillStyle = labelColor;
                ctx.font = "11px Arial";
                ctx.textAlign = "left";
                ctx.fillText(category, legendX + 18, legendY + i * legendSpacing);

                var percent = ((spent[i] / totalSpent) * 100).toFixed(1);
                ctx.fillStyle = labelColor;
                ctx.font = "bold 10px Arial";
                ctx.fillText(percent + "%", legendX + 80, legendY + i * legendSpacing);

                ctx.fillStyle = labelColor;
                ctx.font = "10px Arial";
                ctx.fillText("$" + spent[i].toFixed(2), legendX + 120, legendY + i * legendSpacing);

                currentAngle += sliceAngle;
            });
        }

        function getSliceAtPoint(x, y) {
            var dx = x - centerX;
            var dy = y - centerY;
            var distance = Math.sqrt(dx * dx + dy * dy);

            if (distance > radius + liftAmount) {
                return -1;
            }

            var angle = Math.atan2(dy, dx);
            if (angle < -Math.PI / 2) angle += 2 * Math.PI;
            angle += Math.PI / 2; // Adjust for starting at top

            var currentAngle = 0;
            for (var i = 0; i < categories.length; i++) {
                var sliceAngle = (spent[i] / totalSpent) * 2 * Math.PI;
                if (angle >= currentAngle && angle <= currentAngle + sliceAngle) {
                    return i;
                }
                currentAngle += sliceAngle;
            }
            return -1;
        }

        budgetCanvas.addEventListener('mousemove', function(e) {
            var rect = budgetCanvas.getBoundingClientRect();
            var x = e.clientX - rect.left;
            var y = e.clientY - rect.top;
            var newHoveredIndex = getSliceAtPoint(x, y);

            if (newHoveredIndex !== hoveredIndex) {
                hoveredIndex = newHoveredIndex;
                budgetCanvas.style.cursor = hoveredIndex >= 0 ? 'pointer' : 'default';
                drawChart();
            }
        });

        budgetCanvas.addEventListener('mouseleave', function() {
            hoveredIndex = -1;
            budgetCanvas.style.cursor = 'default';
            drawChart();
        });

        drawChart();

        if (!window.redrawCharts) {
            window.redrawCharts = function() {};
        }
        var originalRedraw = window.redrawCharts;
        window.redrawCharts = function() {
            if (originalRedraw) originalRedraw();
            isDark = document.body.classList.contains('dark-mode');
            chartColors = isDark ? darkColors : colors;
            labelColor = isDark ? '#cbd5e1' : '#6b7280';
            drawChart();
        };
    }

    var goalForm = document.getElementById("goalForm");
    if (goalForm) {
        goalForm.addEventListener("submit", function (e) {
            e.preventDefault();
            goalForm.reset();
        });
    }

});
