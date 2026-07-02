<?php
require_once '../config/db.php';
require_once '../includes/auth_functions.php';
check_role(['admin', 'cashier']);

$page_title = 'Управление врачами';
include '../includes/header.php';
?>

<div id="app" v-cloak>

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="fw-bold mb-0 text-secondary"><i class="fas fa-user-md me-2 text-primary"></i>Штат врачей</h5>
        <div class="d-flex gap-2">
            <button v-if="role === 'admin' || role === 'cashier'" class="btn btn-outline-secondary btn-sm" @click="openSpecialtiesModal">
                <i class="fas fa-tags me-2"></i>Специальности
                <span class="badge bg-secondary ms-1">{{ specialties.length }}</span>
            </button>
            <button v-if="role === 'admin' || role === 'cashier'" class="btn btn-primary" @click="openModal()">
                <i class="fas fa-user-md me-2"></i>Добавить врача
            </button>
        </div>
    </div>

    <!-- Doctors Grid -->
    <div class="row row-cols-1 row-cols-md-3 g-4">
        <div class="col" v-for="doctor in doctors" :key="doctor.id">
            <div class="card h-100 border-0 shadow-sm overflow-hidden">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rounded-circle bg-primary bg-opacity-10 text-primary p-3 me-3">
                            <i class="fas fa-user-md fa-2x"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0">{{ doctor.full_name }}</h6>
                            <span class="badge mt-1" :class="doctor.specialty_name ? 'bg-primary bg-opacity-10 text-primary' : 'bg-light text-secondary'">
                                <i class="fas fa-stethoscope me-1" style="font-size:9px;"></i>
                                {{ doctor.specialty_name || 'Без специальности' }}
                            </span>
                        </div>
                    </div>
                    <p class="small text-secondary mb-2"><i class="fas fa-phone me-2 text-muted"></i>{{ doctor.phone }}</p>
                    <p class="small text-secondary mb-3"><i class="fas fa-user-tag me-2 text-muted"></i>{{ doctor.username }}</p>

                    <div class="mb-3">
                        <span v-for="sid in doctor.service_ids" :key="sid" class="badge bg-light text-dark border me-1 mb-1">
                            {{ getServiceName(sid) }}
                        </span>
                    </div>
                </div>
                <div class="card-footer bg-white border-0 d-flex justify-content-between pt-0 pb-3">
                    <button v-if="role === 'admin' || role === 'cashier'" class="btn btn-sm btn-outline-primary px-3" @click="openModal(doctor)"><i class="fas fa-edit me-1"></i>Изменить</button>
                    <button v-if="role === 'admin'" class="btn btn-sm btn-outline-danger px-3" @click="deleteDoctor(doctor.id)"><i class="fas fa-trash me-1"></i>Удалить</button>
                </div>
            </div>
        </div>
        <div class="col-12" v-if="doctors.length === 0">
            <div class="text-center p-5 text-secondary">
                <i class="fas fa-user-md fa-3x opacity-25 mb-3 d-block"></i>
                Врачи ещё не добавлены
            </div>
        </div>
    </div>

    <!-- ===== DOCTOR MODAL ===== -->
    <div class="modal fade" id="doctorModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-user-md me-2"></i>{{ editingId ? 'Редактировать врача' : 'Добавить нового врача' }}</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form @submit.prevent="saveDoctor">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small text-secondary">ФИО врача</label>
                                <input type="text" v-model="form.full_name" class="form-control" placeholder="Фамилия Имя Отчество" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small text-secondary">Специальность</label>
                                <div class="input-group">
                                    <select v-model="form.specialty_id" class="form-select">
                                        <option value="">-- Без специальности --</option>
                                        <option v-for="sp in specialties" :key="sp.id" :value="sp.id">{{ sp.name }}</option>
                                    </select>
                                    <button type="button" class="btn btn-outline-secondary" @click="openSpecialtiesModal" title="Управление специальностями">
                                        <i class="fas fa-cog"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small text-secondary">Телефон</label>
                                <input type="text" v-model="form.phone" class="form-control" placeholder="+998 90 000 00 00" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small text-secondary">Логин (для входа)</label>
                                <input type="text" v-model="form.username" class="form-control" placeholder="doctor_login" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small text-secondary">Пароль {{ editingId ? '(пусто = без изменений)' : '' }}</label>
                                <input type="password" v-model="form.password" class="form-control" :required="!editingId" minlength="6" maxlength="64">
                                <div class="form-text">Минимум 6 символов, буква + цифра</div>
                            </div>
                        </div>
                        <div class="mt-2">
                            <label class="form-label fw-bold small text-secondary">Оказываемые услуги</label>
                            <div class="row g-2">
                                <div class="col-md-4" v-for="service in allServices" :key="service.id">
                                    <div class="form-check p-2 border rounded-3 text-truncate bg-light bg-opacity-50">
                                        <input class="form-check-input ms-0 me-2" type="checkbox" :value="service.id" v-model="form.service_ids">
                                        <label class="form-check-label small">{{ service.name }}</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>Сохранить данные врача
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== SPECIALTIES MANAGEMENT MODAL ===== -->
    <div class="modal fade" id="specialtiesModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 480px;">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-dark text-white">
                    <h6 class="modal-title fw-bold"><i class="fas fa-tags me-2"></i>Управление специальностями</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- Add New Form -->
                    <div class="input-group mb-4 shadow-sm">
                        <input type="text" v-model="newSpecialtyName" @keyup.enter="addSpecialty"
                               class="form-control border-0 bg-light"
                               placeholder="Название новой специальности...">
                        <button class="btn btn-primary px-4" @click="addSpecialty" :disabled="!newSpecialtyName.trim()">
                            <i class="fas fa-plus me-1"></i> Добавить
                        </button>
                    </div>

                    <!-- List -->
                    <div class="list-group border-0" style="max-height: 350px; overflow-y: auto;">
                        <div v-for="sp in specialties" :key="sp.id"
                             class="list-group-item border-0 border-bottom d-flex align-items-center gap-3 py-2">
                            <!-- Inline Edit -->
                            <i class="fas fa-stethoscope text-primary opacity-50"></i>
                            <input v-if="editingSpecialty && editingSpecialty.id === sp.id"
                                   v-model="editingSpecialty.name"
                                   @keyup.enter="saveSpecialty"
                                   @keyup.escape="editingSpecialty = null"
                                   class="form-control form-control-sm border-primary"
                                   style="flex:1;" autofocus>
                            <span v-else class="fw-semibold flex-grow-1">{{ sp.name }}</span>

                            <div class="btn-group btn-group-sm">
                                <button v-if="editingSpecialty && editingSpecialty.id === sp.id"
                                        class="btn btn-success" @click="saveSpecialty">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button v-else class="btn btn-light border" @click="startEdit(sp)">
                                    <i class="fas fa-edit text-primary"></i>
                                </button>
                                <button class="btn btn-light border" @click="deleteSpecialty(sp.id)">
                                    <i class="fas fa-trash text-danger"></i>
                                </button>
                            </div>
                        </div>
                        <div v-if="specialties.length === 0" class="text-center p-4 text-secondary small">
                            <i class="fas fa-tags fa-2x mb-2 opacity-25 d-block"></i>
                            Нет специальностей. Добавьте первую выше.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
    const { createApp, ref, onMounted } = Vue;

    createApp({
        setup() {
            const doctors       = ref([]);
            const allServices   = ref([]);
            const specialties   = ref([]);
            const editingId     = ref(null);
            const role          = ref('<?= $_SESSION['role'] ?>');
            const form          = ref({ full_name: '', phone: '', username: '', password: '', specialty_id: '', service_ids: [] });

            // Specialty management
            const newSpecialtyName   = ref('');
            const editingSpecialty   = ref(null);

            const fetchData = async () => {
                const results = await Promise.allSettled([
                    axios.get('../api/doctors.php'),
                    axios.get('../api/services.php'),
                    axios.get('../api/specialties.php')
                ]);
                if (results[0].status === 'fulfilled') {
                    doctors.value = results[0].value.data.data || results[0].value.data || [];
                }
                if (results[1].status === 'fulfilled') {
                    allServices.value = results[1].value.data.data || results[1].value.data || [];
                }
                if (results[2].status === 'fulfilled') {
                    specialties.value = results[2].value.data.data || results[2].value.data || [];
                }
                results.forEach((r, i) => {
                    if (r.status === 'rejected') console.error('API error [' + i + ']:', r.reason);
                });
            };

            onMounted(fetchData);

            // ---- DOCTOR ACTIONS ----
            const openModal = (doctor = null) => {
                if (doctor) {
                    editingId.value = doctor.id;
                    form.value = { ...doctor, password: '', specialty_id: doctor.specialty_id || '' };
                } else {
                    editingId.value = null;
                    form.value = { full_name: '', phone: '', username: '', password: '', specialty_id: '', service_ids: [] };
                }
                bootstrap.Modal.getOrCreateInstance(document.getElementById('doctorModal')).show();
            };

            const saveDoctor = async () => {
                if (!editingId.value && (form.value.password.length < 1 || form.value.password.length > 64)) {
                    showToast('Пароль должен быть от 1 до 64 символов', 'warning');
                    return;
                }
                if (editingId.value && form.value.password && (form.value.password.length < 1 || form.value.password.length > 64)) {
                    showToast('Пароль должен быть от 1 до 64 символов', 'warning');
                    return;
                }
                try {
                    const data = editingId.value ? { ...form.value, id: editingId.value } : form.value;
                    await axios.post('../api/doctors.php', data);
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('doctorModal')).hide();
                    showToast('Врач сохранён', 'success');
                    fetchData();
                } catch (e) {
                    showToast('Ошибка сохранения: ' + (e.response?.data?.error || e.message), 'danger');
                }
            };

            const deleteDoctor = async (id) => {
                confirmDelete('Удалить врача?', 'Врач будет удалён из системы.', async () => {
                    try {
                        await axios.delete(`../api/doctors.php?id=${id}`);
                        showToast('Врач удалён', 'success');
                        fetchData();
                    } catch (e) {
                        showToast('Ошибка удаления: ' + (e.response?.data?.error || e.message), 'danger');
                    }
                });
            };

            const getServiceName = (id) => {
                const s = allServices.value.find(x => x.id == id);
                return s ? s.name : '';
            };

            // ---- SPECIALTY ACTIONS ----
            const openSpecialtiesModal = () => {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('doctorModal')).hide();
                bootstrap.Modal.getOrCreateInstance(document.getElementById('specialtiesModal')).show();
            };

            const addSpecialty = async () => {
                const name = newSpecialtyName.value.trim();
                if (!name) return;
                await axios.post('../api/specialties.php', { name });
                newSpecialtyName.value = '';
                await fetchData();
            };

            const startEdit = (sp) => {
                editingSpecialty.value = { ...sp };
            };

            const saveSpecialty = async () => {
                if (!editingSpecialty.value || !editingSpecialty.value.name.trim()) return;
                await axios.post('../api/specialties.php', { id: editingSpecialty.value.id, name: editingSpecialty.value.name });
                editingSpecialty.value = null;
                await fetchData();
            };

            const deleteSpecialty = async (id) => {
                confirmDelete('Удалить специальность?', 'Врачи с этой специальностью станут "Без специальности".', async () => {
                    try {
                        await axios.delete(`../api/specialties.php?id=${id}`);
                        showToast('Специальность удалена', 'success');
                        fetchData();
                    } catch (e) {
                        showToast('Ошибка удаления: ' + (e.response?.data?.error || e.message), 'danger');
                    }
                });
            };



            return {
                doctors, allServices, specialties, editingId, form, role,
                newSpecialtyName, editingSpecialty,
                openModal, saveDoctor, deleteDoctor, getServiceName,
                openSpecialtiesModal, addSpecialty, startEdit, saveSpecialty, deleteSpecialty
            };
        }
    }).mount('#app');
</script>

<?php include '../includes/footer.php'; ?>
