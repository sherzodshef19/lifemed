<?php
require_once '../config/db.php';
require_once '../includes/auth_functions.php';
check_role(['admin', 'cashier']);

$page_title = 'Результаты анализов';
include '../includes/header.php';
?>

<div id="app" v-cloak>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="search-box w-50">
            <div class="input-group shadow-sm" style="border-radius: 0.75rem; overflow: hidden;">
                <span class="input-group-text bg-white border-0 ps-3"><i class="fas fa-search text-secondary"></i></span>
                <input type="text" v-model="searchQuery" class="form-control border-0 py-2 shadow-none" placeholder="Поиск по пациенту или анализу...">
            </div>
        </div>
        <div class="d-flex gap-2">
             <select v-model="filterTemplate" class="form-select border-0 shadow-sm rounded-pill px-3">
                 <option value="">Все типы</option>
                 <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.title }}</option>
             </select>
        </div>
    </div>

    <div class="card border-0 shadow-sm" style="border-radius: 1rem;">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-secondary small text-uppercase">
                        <tr>
                            <th class="ps-4">ID Пациента</th>
                            <th>Пациент</th>
                            <th>Тип обследования</th>
                            <th>Врач</th>
                            <th>Дата / Время</th>
                            <th class="text-end pe-4">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="res in results" :key="res.id">
                            <td class="ps-4">
                                <span class="badge bg-light text-dark border">{{ String(res.patient_id).padStart(5, '0') }}</span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">{{ res.patient_name }}</div>
                            </td>
                            <td>
                                <span class="badge bg-primary bg-opacity-10 text-primary px-3">{{ res.template_title }}</span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-2" style="width: 28px; height: 28px;">
                                        <i class="fas fa-user-md text-secondary" style="font-size: 0.8rem;"></i>
                                    </div>
                                    <span class="small">{{ res.doctor_name || '—' }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="fw-medium">{{ formatDate(res.created_at) }}</div>
                                <div class="text-muted small" style="font-size: 0.7rem;">{{ formatTime(res.created_at) }}</div>
                            </td>
                            <td class="text-end pe-4">
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-light text-primary me-2 rounded-circle" @click="viewResult(res)" title="Посмотреть">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button v-if="role === 'admin'" class="btn btn-sm btn-light text-danger rounded-circle" @click="deleteResult(res.id)" title="Удалить">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="results.length === 0">
                            <td colspan="6" class="text-center py-5 text-secondary">
                                <i class="fas fa-microscope fa-3x mb-3 opacity-25"></i><br>
                                Результаты не найдены
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <div v-if="totalPages > 1" class="d-flex justify-content-center mt-4">
        <nav>
            <ul class="pagination pagination-sm shadow-sm">
                <li class="page-item" :class="{ disabled: currentPage === 1 }">
                    <button class="page-link" @click="changePage(1)"><i class="fas fa-angle-double-left"></i></button>
                </li>
                <li class="page-item" :class="{ disabled: currentPage === 1 }">
                    <button class="page-link" @click="changePage(currentPage - 1)"><i class="fas fa-chevron-left"></i></button>
                </li>
                <li v-for="p in visiblePages" :key="p" class="page-item" :class="{ active: p === currentPage }">
                    <button class="page-link px-3" @click="changePage(p)">{{ p }}</button>
                </li>
                <li class="page-item" :class="{ disabled: currentPage === totalPages }">
                    <button class="page-link" @click="changePage(currentPage + 1)"><i class="fas fa-chevron-right"></i></button>
                </li>
                <li class="page-item" :class="{ disabled: currentPage === totalPages }">
                    <button class="page-link" @click="changePage(totalPages)"><i class="fas fa-angle-double-right"></i></button>
                </li>
            </ul>
        </nav>
    </div>
</div>

<script>
    const { createApp, ref, computed, onMounted, watch } = Vue;

    createApp({
        setup() {
            const results = ref([]);
            const templates = ref([]);
            const searchQuery = ref('');
            const filterTemplate = ref('');
            const currentPage = ref(1);
            const totalPages = ref(1);
            const role = ref('<?= $_SESSION['role'] ?>');

            const visiblePages = computed(() => {
                const total = parseInt(totalPages.value) || 1;
                const current = parseInt(currentPage.value) || 1;
                const pages = [];
                let start = Math.max(1, current - 2);
                let end = Math.min(total, current + 2);
                if (end - start < 4) {
                    if (start === 1) end = Math.min(total, start + 4);
                    else start = Math.max(1, end - 4);
                }
                for (let i = start; i <= end; i++) pages.push(i);
                return pages;
            });

            const fetchResults = async () => {
                const settled = await Promise.allSettled([
                    axios.get(`../api/lab_results.php?q=${searchQuery.value}&page=${currentPage.value}&template_id=${filterTemplate.value}`),
                    axios.get('../api/lab_templates.php')
                ]);
                if (settled[0].status === 'fulfilled') {
                    const rPayload = settled[0].value.data.data || settled[0].value.data;
                    results.value = rPayload.data || rPayload || [];
                    totalPages.value = parseInt(rPayload.pages) || 1;
                    const pg = parseInt(rPayload.page) || 1;
                    if (pg !== currentPage.value) currentPage.value = pg;
                }
                if (settled[1].status === 'fulfilled') {
                    const tPayload = settled[1].value.data.data || settled[1].value.data;
                    templates.value = Array.isArray(tPayload) ? tPayload : [];
                }
            };

            const changePage = (p) => {
                const total = parseInt(totalPages.value) || 1;
                if (p < 1 || p > total) return;
                currentPage.value = p;
                fetchResults();
            };

            let debounceTimeout;
            watch([searchQuery, filterTemplate], () => {
                clearTimeout(debounceTimeout);
                debounceTimeout = setTimeout(() => {
                    currentPage.value = 1;
                    fetchResults();
                }, 300);
            });

            const viewResult = (res) => {
                window.open(`lab_print.php?patient_id=${res.patient_id}&template_id=${res.template_id}&result_id=${res.id}`, '_blank');
            };

            const deleteResult = async (id) => {
                confirmDelete('Удалить результат?', 'Результат будет удалён безвозвратно.', async () => {
                    try {
                        await axios.delete(`../api/lab_results.php?id=${id}`);
                        showToast('Результат удалён', 'success');
                        fetchResults();
                    } catch (e) {
                        showToast('Ошибка удаления: ' + (e.response?.data?.error || e.message), 'danger');
                    }
                });
            };

            const formatDate = (dateStr) => new Date(dateStr).toLocaleDateString('ru-RU');
            const formatTime = (dateStr) => new Date(dateStr).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });

            onMounted(fetchResults);

            return { 
                results, templates, searchQuery, filterTemplate, role,
                currentPage, totalPages, visiblePages, changePage,
                viewResult, deleteResult, formatDate, formatTime 
            };
        }
    }).mount('#app');
</script>

<?php include '../includes/footer.php'; ?>
