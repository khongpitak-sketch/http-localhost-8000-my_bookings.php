/**
 * Table Sorter & Paginator (10 Records / Page & Asc/Desc Toggle)
 * ระบบจัดเรียงตารางและแบ่งหน้าอัตโนมัติ (10 แร็คคอร์ดต่อหน้า พร้อมปุ่ม ถัดไป และ ย้อนกลับ)
 * สลับลำดับ น้อยไปหามาก <-> มากไปหาน้อย เมื่อกดที่ตัวอักษรหัวตาราง
 */

(function(window, document) {
    'use strict';

    function initTablePaginationAndSort(tableElementOrId, customOptions) {
        var table = typeof tableElementOrId === 'string'
            ? document.getElementById(tableElementOrId)
            : tableElementOrId;

        if (!table) return null;

        var options = Object.assign({
            pageSize: 10,
            initialPage: 1,
            excludeClass: 'no-sort'
        }, customOptions || {});

        var tbody = table.querySelector('tbody');
        if (!tbody) return null;

        // ดึงเฉพาะแถวข้อมูลจริง (ไม่นับแถว colspan ที่เป็นข้อความว่างเปล่า)
        var allRows = Array.from(tbody.querySelectorAll('tr')).filter(function(tr) {
            return !tr.querySelector('td[colspan]');
        });

        if (allRows.length === 0) {
            return null;
        }

        var rows = allRows.slice(); // ทำสำเนาแถวสำหรับเรียงลำดับ
        var headers = Array.from(table.querySelectorAll('thead th'));
        var currentSortCol = -1;
        var currentSortDir = 'asc'; // 'asc' = น้อยไปหามาก, 'desc' = มากไปหาน้อย
        var currentPage = options.initialPage;
        var pageSize = options.pageSize;

        // ค้นหาหรือสร้างแถบควบคุมการแบ่งหน้า (Pagination Toolbar)
        var wrapper = table.closest('.table-responsive') || table;
        var toolbarId = 'paginator-' + (table.id || Math.random().toString(36).substr(2, 9));
        var toolbar = wrapper.parentElement.querySelector('#' + toolbarId);

        if (!toolbar) {
            toolbar = document.createElement('div');
            toolbar.id = toolbarId;
            toolbar.className = 'table-pagination-toolbar';
            wrapper.parentNode.insertBefore(toolbar, wrapper.nextSibling);
        }

        // ดึงค่าสำหรับเปรียบเทียบใน Cell
        function getCellValue(tr, colIndex) {
            var cell = tr.children[colIndex];
            if (!cell) return '';
            if (cell.getAttribute('data-sort-value') !== null) {
                return cell.getAttribute('data-sort-value');
            }
            if (cell.dataset && cell.dataset.sortValue !== undefined) {
                return cell.dataset.sortValue;
            }
            return cell.innerText.trim();
        }

        // ฟังก์ชันเรียงลำดับข้อมูลในแถว
        function sortRows(colIndex, dir) {
            rows.sort(function(rowA, rowB) {
                var valA = getCellValue(rowA, colIndex);
                var valB = getCellValue(rowB, colIndex);

                // ตรวจสอบว่าเป็นตัวเลขล้วนหรือไม่
                var cleanA = String(valA).replace(/,/g, '').trim();
                var cleanB = String(valB).replace(/,/g, '').trim();
                var numA = parseFloat(cleanA);
                var numB = parseFloat(cleanB);
                var isNumA = !isNaN(numA) && isFinite(cleanA) && cleanA !== '';
                var isNumB = !isNaN(numB) && isFinite(cleanB) && cleanB !== '';

                var comp = 0;
                if (isNumA && isNumB) {
                    comp = numA - numB;
                } else {
                    // ใช้ localeCompare ภาษาไทย รองรับตัวเลขปนข้อความ
                    comp = String(valA).localeCompare(String(valB), 'th', { numeric: true, sensitivity: 'base' });
                }

                return dir === 'asc' ? comp : -comp;
            });

            // ใส่แถวที่เรียงลำดับใหม่กลับเข้าสู่ tbody
            rows.forEach(function(r) {
                tbody.appendChild(r);
            });
        }

        // อัปเดตไอคอนและสถานะหัวตาราง
        function updateHeaderUI() {
            headers.forEach(function(th, idx) {
                var text = th.innerText.trim();
                if (
                    th.classList.contains(options.excludeClass) ||
                    th.hasAttribute('no-sort') ||
                    text === 'จัดการ' ||
                    text === 'ดำเนินการ' ||
                    text === 'การจัดการ'
                ) {
                    return;
                }

                var iconSpan = th.querySelector('.sort-icon-wrapper');
                th.classList.remove('sorted-asc', 'sorted-desc');

                if (idx === currentSortCol) {
                    if (currentSortDir === 'asc') {
                        th.classList.add('sorted-asc');
                        th.setAttribute('title', 'กำลังเรียง: น้อยไปหามาก (คลิกเพื่อสลับเป็น: มากไปหาน้อย)');
                        if (iconSpan) {
                            iconSpan.innerHTML = '<i class="fas fa-sort-up text-primary sort-icon ms-1"></i>';
                        }
                    } else {
                        th.classList.add('sorted-desc');
                        th.setAttribute('title', 'กำลังเรียง: มากไปหาน้อย (คลิกเพื่อสลับเป็น: น้อยไปหามาก)');
                        if (iconSpan) {
                            iconSpan.innerHTML = '<i class="fas fa-sort-down text-primary sort-icon ms-1"></i>';
                        }
                    }
                } else {
                    th.setAttribute('title', 'คลิกเพื่อเรียงลำดับ น้อยไปหามาก / มากไปหาน้อย');
                    if (iconSpan) {
                        iconSpan.innerHTML = '<i class="fas fa-sort text-muted-opacity sort-icon ms-1"></i>';
                    }
                }
            });
        }

        // แสดงผลตารางเฉพาะหน้าที่เลือก (10 แร็คคอร์ดต่อหน้า)
        function renderPagination() {
            var totalRows = rows.length;
            var totalPages = Math.max(1, Math.ceil(totalRows / pageSize));

            if (currentPage > totalPages) currentPage = totalPages;
            if (currentPage < 1) currentPage = 1;

            var startIndex = (currentPage - 1) * pageSize;
            var endIndex = Math.min(startIndex + pageSize, totalRows);

            // ซ่อนหรือแสดงแถวตามหน้าที่เลือก
            rows.forEach(function(row, i) {
                if (i >= startIndex && i < endIndex) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });

            var startRecordDisplay = totalRows === 0 ? 0 : startIndex + 1;
            var endRecordDisplay = endIndex;

            // สร้างปุ่มเลขหน้า
            var maxButtons = 5;
            var startPage = Math.max(1, currentPage - 2);
            var endPage = Math.min(totalPages, startPage + maxButtons - 1);
            if (endPage - startPage < maxButtons - 1) {
                startPage = Math.max(1, endPage - maxButtons + 1);
            }

            var pageButtonsHtml = '';

            if (startPage > 1) {
                pageButtonsHtml += '<li class="page-item"><button type="button" class="page-link" data-page="1">1</button></li>';
                if (startPage > 2) {
                    pageButtonsHtml += '<li class="page-item disabled"><span class="page-link border-0">...</span></li>';
                }
            }

            for (var p = startPage; p <= endPage; p++) {
                pageButtonsHtml += '<li class="page-item ' + (p === currentPage ? 'active' : '') + '">' +
                    '<button type="button" class="page-link" data-page="' + p + '">' + p + '</button>' +
                    '</li>';
            }

            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    pageButtonsHtml += '<li class="page-item disabled"><span class="page-link border-0">...</span></li>';
                }
                pageButtonsHtml += '<li class="page-item"><button type="button" class="page-link" data-page="' + totalPages + '">' + totalPages + '</button></li>';
            }

            var isPrevDisabled = currentPage <= 1;
            var isNextDisabled = currentPage >= totalPages;

            toolbar.innerHTML = 
                '<div class="page-info">' +
                    'แสดง <strong class="text-dark">' + startRecordDisplay + ' - ' + endRecordDisplay + '</strong> ' +
                    'จากทั้งหมด <strong class="text-dark">' + totalRows + '</strong> รายการ' +
                    '<span class="badge bg-light text-secondary border ms-2">หน้า ' + currentPage + ' / ' + totalPages + '</span>' +
                '</div>' +
                '<nav aria-label="การแบ่งหน้าตาราง">' +
                    '<ul class="pagination pagination-sm mb-0">' +
                        '<li class="page-item ' + (isPrevDisabled ? 'disabled' : '') + '">' +
                            '<button type="button" class="page-link btn-prev" ' + (isPrevDisabled ? 'disabled' : '') + '>' +
                                '<i class="fas fa-chevron-left me-1"></i> ย้อนกลับ' +
                            '</button>' +
                        '</li>' +
                        pageButtonsHtml +
                        '<li class="page-item ' + (isNextDisabled ? 'disabled' : '') + '">' +
                            '<button type="button" class="page-link btn-next" ' + (isNextDisabled ? 'disabled' : '') + '>' +
                                'ถัดไป <i class="fas fa-chevron-right ms-1"></i>' +
                            '</button>' +
                        '</li>' +
                    '</ul>' +
                '</nav>';

            // ผูกเหตุการณ์คลิกปุ่มย้อนกลับ
            var prevBtn = toolbar.querySelector('.btn-prev');
            if (prevBtn && !isPrevDisabled) {
                prevBtn.addEventListener('click', function() {
                    if (currentPage > 1) {
                        currentPage--;
                        renderPagination();
                    }
                });
            }

            // ผูกเหตุการณ์คลิกปุ่มถัดไป
            var nextBtn = toolbar.querySelector('.btn-next');
            if (nextBtn && !isNextDisabled) {
                nextBtn.addEventListener('click', function() {
                    if (currentPage < totalPages) {
                        currentPage++;
                        renderPagination();
                    }
                });
            }

            // ผูกเหตุการณ์คลิกตัวเลขหน้า
            toolbar.querySelectorAll('button[data-page]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var targetPage = parseInt(btn.getAttribute('data-page'), 10);
                    if (!isNaN(targetPage) && targetPage !== currentPage) {
                        currentPage = targetPage;
                        renderPagination();
                    }
                });
            });
        }

        // ตั้งค่าคลิกที่หัวตาราง (Toggle น้อยไปหามาก <-> มากไปหาน้อย)
        headers.forEach(function(th, colIdx) {
            var text = th.innerText.trim();
            if (
                th.classList.contains(options.excludeClass) ||
                th.hasAttribute('no-sort') ||
                text === 'จัดการ' ||
                text === 'ดำเนินการ' ||
                text === 'การจัดการ'
            ) {
                return;
            }

            th.classList.add('sortable-header');
            th.setAttribute('role', 'button');
            th.setAttribute('tabindex', '0');

            var iconSpan = th.querySelector('.sort-icon-wrapper');
            if (!iconSpan) {
                iconSpan = document.createElement('span');
                iconSpan.className = 'sort-icon-wrapper';
                iconSpan.innerHTML = '<i class="fas fa-sort text-muted-opacity sort-icon ms-1"></i>';
                th.appendChild(iconSpan);
            }

            function triggerSort() {
                if (currentSortCol === colIdx) {
                    // กดซ้ำคอลัมน์เดิม ให้สลับ (Toggle) น้อยไปหามาก <-> มากไปหาน้อย
                    currentSortDir = (currentSortDir === 'asc') ? 'desc' : 'asc';
                } else {
                    currentSortCol = colIdx;
                    currentSortDir = 'asc';
                }
                sortRows(currentSortCol, currentSortDir);
                updateHeaderUI();
                currentPage = 1; // สลับการเรียงแล้วให้กลับไปหน้าแรก
                renderPagination();
            }

            th.addEventListener('click', function(e) {
                if (e.target.closest('button, a, input, select, form')) return;
                triggerSort();
            });

            th.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    triggerSort();
                }
            });
        });

        // เริ่มต้นการทำงาน
        updateHeaderUI();
        renderPagination();

        return {
            sort: function(colIndex, dir) {
                currentSortCol = colIndex;
                currentSortDir = dir || 'asc';
                sortRows(colIndex, currentSortDir);
                updateHeaderUI();
                renderPagination();
            },
            goToPage: function(p) {
                currentPage = p;
                renderPagination();
            },
            refresh: function() {
                allRows = Array.from(tbody.querySelectorAll('tr')).filter(function(tr) {
                    return !tr.querySelector('td[colspan]');
                });
                rows = allRows.slice();
                if (currentSortCol >= 0) {
                    sortRows(currentSortCol, currentSortDir);
                }
                renderPagination();
            }
        };
    }

    // Auto-init สำหรับตารางที่มีคลาส .table-sortable-paginated หรือ attribute data-table-sortable
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('table.table-sortable-paginated, table[data-table-sortable]').forEach(function(table) {
            var pageSize = parseInt(table.getAttribute('data-page-size') || '10', 10);
            initTablePaginationAndSort(table, { pageSize: pageSize || 10 });
        });
    });

    window.initTablePaginationAndSort = initTablePaginationAndSort;

})(window, document);
