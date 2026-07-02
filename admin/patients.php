<?php
require_once '../config/db.php';
require_once '../includes/auth_functions.php';
check_role(['admin', 'cashier']);

$page_title = 'Управление пациентами';
include '../includes/header.php';
?>

<div id="app" v-cloak>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="search-box w-50">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-secondary"></i></span>
                <input type="text" v-model="searchQuery" class="form-control border-start-0" placeholder="Поиск по ID, ФИО или номеру телефона...">
            </div>
        </div>
        <button class="btn btn-primary shadow-sm" @click="openModal()"><i class="fas fa-plus-circle me-2"></i> Добавить пациента</button>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>ФИО</th>
                            <th>Дата рождения</th>
                            <th>Телефон</th>
                            <th>Адрес</th>
                            <th>Регистрация</th>
                            <th class="text-end pe-4">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="patient in patients" :key="patient.id">
                            <td class="ps-4">
                                <span class="badge bg-light text-dark border">{{ String(patient.id).padStart(5, '0') }}</span>
                            </td>
                            <td>
                                <div class="fw-bold">{{ patient.full_name }}</div>
                            </td>
                            <td>{{ patient.dob }}</td>
                            <td>{{ patient.phone }}</td>
                            <td class="text-truncate" style="max-width: 200px;">{{ patient.address || '—' }}</td>
                            <td>{{ formatDate(patient.registration_date) }}</td>
                            <td class="text-end pe-4">
                                <a :href="'patient_history.php?id=' + patient.id" class="btn btn-sm btn-light text-info me-2" title="История посещений">
                                    <i class="fas fa-history"></i>
                                </a>
                                <button class="btn btn-sm btn-light text-primary me-2" @click="openModal(patient)"><i class="fas fa-edit"></i></button>
                                <button v-if="role === 'admin'" class="btn btn-sm btn-light text-danger" @click="deletePatient(patient.id)"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                        <tr v-if="patients.length === 0">
                            <td colspan="7" class="text-center py-5 text-secondary">
                                <i class="fas fa-user-slash fa-3x mb-3 d-block"></i>
                                Пациенты не найдены
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
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

    <!-- Modal Form -->
    <div class="modal fade" id="patientModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 1rem;">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">{{ editingId ? 'Редактировать' : 'Добавить' }} пациента</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form @submit.prevent="savePatient">
                        <div class="mb-3">
                            <label class="form-label small text-secondary">ФИО</label>
                            <input type="text" v-model="form.full_name" class="form-control" required placeholder="Иванов Иван Иванович">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label small text-secondary">Дата рождения</label>
                                <input type="date" v-model="form.dob" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small text-secondary">Телефон</label>
                                <input type="text" v-model="form.phone" class="form-control" required placeholder="+998 90 123-45-67">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-secondary">Адрес</label>
                            <textarea v-model="form.address" class="form-control" rows="2" placeholder="г. Ташкент, ул. Навои..."></textarea>
                        </div>
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary" :disabled="loading">
                                <span v-if="loading" class="spinner-border spinner-border-sm me-2"></span>
                                Сохранить
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const { createApp, ref, computed, onMounted, watch } = Vue;

    createApp({
        setup() {
            const patients = ref([]);
            const searchQuery = ref('');
            const loading = ref(false);
            const editingId = ref(null);
            const currentPage = ref(1);
            const totalPages = ref(1);
            const role = ref('<?= $_SESSION['role'] ?>');
            
            const form = ref({ full_name: '', dob: '', phone: '', address: '' });

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

            const fetchPatients = async () => {
                loading.value = true;
                try {
                    const params = new URLSearchParams({ q: searchQuery.value, page: currentPage.value });
                    const res = await axios.get(`../api/patients.php?${params}`);
                    const payload = res.data.data || res.data;
                    patients.value = payload.data || payload || [];
                    totalPages.value = parseInt(payload.pages) || 1;
                    const pg = parseInt(payload.page) || 1;
                    if (pg !== currentPage.value) currentPage.value = pg;
                } catch (e) {
                    console.error('Ошибка загрузки пациентов:', e);
                } finally {
                    loading.value = false;
                }
            };

            const changePage = (p) => {
                const total = parseInt(totalPages.value) || 1;
                if (p < 1 || p > total) return;
                currentPage.value = p;
                fetchPatients();
            };

            // Reset to page 1 on search
            let debounceTimeout;
            watch(searchQuery, () => {
                clearTimeout(debounceTimeout);
                debounceTimeout = setTimeout(() => {
                    currentPage.value = 1;
                    fetchPatients();
                }, 400);
            });
            
            onMounted(fetchPatients);
            
            const openModal = (patient = null) => {
                if (patient) {
                    editingId.value = patient.id;
                    form.value = { ...patient };
                } else {
                    editingId.value = null;
                    form.value = { full_name: '', dob: '', phone: '', address: '' };
                }
                bootstrap.Modal.getOrCreateInstance(document.getElementById('patientModal')).show();
            };

            const savePatient = async () => {
                loading.value = true;
                try {
                    const data = editingId.value ? { ...form.value, id: editingId.value } : form.value;
                    await axios.post('../api/patients.php', data);
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('patientModal')).hide();
                    showToast('Пациент сохранён', 'success');
                    fetchPatients();
                } catch (e) {
                    showToast('Ошибка сохранения: ' + (e.response?.data?.error || e.message), 'danger');
                } finally {
                    loading.value = false;
                }
            };

            const deletePatient = async (id) => {
                confirmDelete('Удалить пациента?', 'Это действие нельзя отменить.', async () => {
                    try {
                        await axios.delete(`../api/patients.php?id=${id}`);
                        showToast('Пациент удалён', 'success');
                        fetchPatients();
                    } catch (e) {
                        showToast('Ошибка удаления: ' + (e.response?.data?.error || e.message), 'danger');
                    }
                });
            };

            const formatDate = (dateStr) => {
                if (!dateStr) return '—';
                return new Date(dateStr).toLocaleDateString('ru-RU');
            };



            return { 
                patients, searchQuery, loading, editingId, form, role,
                currentPage, totalPages, visiblePages, changePage,
                openModal, savePatient, deletePatient, formatDate, fetchPatients 
            };
        }
    }).mount('#app');
</script>

<?php include '../includes/footer.php'; ?>
