<?php
require_once '../config/db.php';
require_once '../includes/auth_functions.php';
check_role(['admin', 'doctor']);

// Get specialty of current doctor (if logged in as doctor)
$doctorSpecialty = '';
if ($_SESSION['role'] === 'doctor' && !empty($_SESSION['doctor_id'])) {
    $stmt = $pdo->prepare("
        SELECT sp.name FROM doctors d
        LEFT JOIN specialties sp ON d.specialty_id = sp.id
        WHERE d.id = ?
    ");
    $stmt->execute([$_SESSION['doctor_id']]);
    $row = $stmt->fetch();
    $doctorSpecialty = $row['name'] ?? '';
}

$page_title = 'Бланки анализов';
include '../includes/header.php';
?>

<style>
    .list-item-hover:hover { background-color: #f8f9fa; cursor: pointer; }
    .pointer { cursor: pointer; }
    .lab-content-container table { width: 100%; border-collapse: collapse; }
    .lab-content-container table td,
    .lab-content-container table th { padding: 4px 8px; border: 1px solid #dee2e6; }
    .lab-content-container input[type="text"],
    .lab-content-container textarea { border: 1px solid #ced4da; border-radius: 4px; padding: 2px 6px; min-width: 80px; }
</style>

<div id="app" v-cloak>
    <div class="row">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0">Шаблоны по направлениям</h6>
                </div>
                <div class="card-body p-0">
                    <!-- Template Search -->
                    <div class="px-3 pt-3 pb-2">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light border-0"><i class="fas fa-search text-secondary small"></i></span>
                            <input type="text" v-model="templateSearch" class="form-control bg-light border-0" placeholder="Поиск шаблона...">
                            <button v-if="templateSearch" class="btn btn-light border-0 text-secondary" @click="templateSearch = ''"><i class="fas fa-times"></i></button>
                        </div>
                    </div>

                    <!-- Category Tabs (from Specialties table) -->
                    <!-- Admins see all tabs. Doctors only see their specialty. -->
                    <div class="nav nav-pills nav-fill bg-light p-1 flex-wrap" style="font-size: 11px;">
                        <?php if ($_SESSION['role'] !== 'doctor'): ?>
                        <button class="nav-link py-2" :class="{ active: activeCategory === 'all' }" @click="activeCategory = 'all'">Все</button>
                        <button v-for="sp in specialties" :key="sp.id"
                                class="nav-link py-2"
                                :class="{ active: activeCategory === sp.name }"
                                @click="activeCategory = sp.name">
                            {{ sp.name }}
                        </button>
                        <?php else: ?>
                        <span class="nav-link active py-2 fw-bold">
                            <i class="fas fa-stethoscope me-1"></i>
                            <?= htmlspecialchars($doctorSpecialty ?: 'Мои бланки') ?>
                        </span>
                        <?php endif; ?>
                    </div>

                    <div class="list-group list-group-flush mt-2" style="max-height: 600px; overflow-y: auto;">
                        <div v-for="tmpl in filteredTemplates" :key="tmpl.id"
                             class="list-group-item list-group-item-action d-flex justify-content-between align-items-center bg-transparent border-0 py-3 border-bottom list-item-hover">
                            <div @click="selectTmpl(tmpl)" class="flex-grow-1 pointer pe-3">
                                <span class="badge bg-opacity-10 text-uppercase mb-1"
                                      :class="categoryBadgeClass(tmpl.category)" style="font-size: 9px;">
                                    {{ tmpl.category }}
                                </span>
                                <h6 class="mb-0 fw-bold" style="font-size: 14px;">{{ tmpl.title }}</h6>
                                <small class="text-secondary" style="font-size: 10px;">{{ formatDate(tmpl.created_at) }}</small>
                            </div>
                            <?php if ($_SESSION['role'] !== 'doctor'): ?>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-light border" @click.stop="editTemplate(tmpl)" title="Редактировать"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-sm btn-light border text-success" @click.stop="cloneTemplate(tmpl)" title="Дублировать"><i class="fas fa-copy"></i></button>
                                <button class="btn btn-sm btn-light border text-danger" @click.stop="deleteTemplate(tmpl.id)" title="Удалить"><i class="fas fa-trash"></i></button>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div v-if="filteredTemplates.length === 0" class="p-5 text-center text-secondary small">
                            <i class="fas fa-folder-open fa-2x mb-2 opacity-25"></i><br>Нет шаблонов в этой категории
                        </div>
                    </div>
                    <?php if ($_SESSION['role'] !== 'doctor'): ?>
                    <div class="p-3">
                        <button class="btn btn-primary btn-sm w-100 shadow-sm" @click="newTemplate">
                            <i class="fas fa-plus me-2"></i>Создать новый бланк
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <!-- 1. EDITING TEMPLATE MODE (Admin only) -->
            <?php if ($_SESSION['role'] !== 'doctor'): ?>
            <div class="card border-0 shadow-sm" v-if="editingTmpl">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-primary"><i class="fas fa-edit me-2"></i>{{ editingTmpl.id ? 'Настройка шаблона' : 'Новый шаблон' }}</h6>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-outline-secondary px-3" @click="editingTmpl = null">Отмена</button>
                        <button class="btn btn-sm btn-primary px-4" @click="saveTemplate">Сохранить шаблон</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-bold text-secondary">Название бланка</label>
                            <input type="text" v-model="editingTmpl.title" class="form-control border-0 bg-light" placeholder="Например: УЗИ брюшной полости">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary">Направление (Специальность)</label>
                            <select v-model="editingTmpl.category" class="form-select border-0 bg-light">
                                <option value="">-- Без направления --</option>
                                <option v-for="sp in specialties" :key="sp.id" :value="sp.name">{{ sp.name }}</option>
                            </select>
                            <div class="form-text small">
                                <a href="doctors.php" target="_blank" class="text-secondary">
                                    <i class="fas fa-cog me-1"></i>Управление специальностями
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Структура бланка (HTML)</label>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <textarea v-model="editingTmpl.content" class="form-control font-monospace border-0 bg-light" rows="15" style="font-size: 13px;"></textarea>
                            </div>
                            <div class="col-md-6">
                                <div class="border rounded-3 bg-white p-3" style="height: 100%; min-height: 300px; overflow-y: auto;">
                                    <div class="small text-muted mb-2 fw-bold"><i class="fas fa-eye me-1"></i>Предпросмотр</div>
                                    <div v-html="editingTmpl.content || '<span class=text-muted>Начните вводить HTML...</span>'"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- 2. FILLING ANALYSIS MODE -->
            <div class="card border-0 shadow-sm" v-if="!editingTmpl && selectedTmplId && selectedPatientId">
                <!-- Warning for old appointments (doctor only) -->
                <div v-if="!canFillAnalysis" class="alert alert-danger d-flex align-items-center m-0 rounded-0" role="alert">
                    <i class="fas fa-ban me-2 fa-lg"></i>
                    <div>
                        <strong>Анализ недоступен.</strong> Приём был <strong>{{ appointmentDate }}</strong> — можно заполнять анализы только за сегодня.
                        <br><small class="text-muted">Обратитесь к администратору, если нужно создать анализ задним числом.</small>
                    </div>
                </div>
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="fw-bold mb-0 text-success"><i class="fas fa-file-medical me-2"></i>{{ selectedTmpl.title }}</h6>
                        <small class="text-secondary">Пациент: {{ selectedPatient.full_name }}</small>
                    </div>
                    <div class="btn-group">
                         <button class="btn btn-sm btn-outline-secondary px-3" @click="selectedTmplId = ''">Отмена</button>
                         <button class="btn btn-sm btn-success px-4" @click="saveAndPrint" :disabled="!canFillAnalysis"><i class="fas fa-print me-1"></i> Сохранить и Печатать</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="px-4 py-3 bg-light bg-opacity-50 border-bottom d-flex justify-content-between align-items-center">
                        <div class="small text-secondary">
                            <i class="fas fa-keyboard me-2 text-success"></i>
                            Заполните поля таблицы и нажмите «Сохранить и Печатать».
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill">
                                <i class="fas fa-user-md me-1"></i>
                                <?= htmlspecialchars($_SESSION['full_name'] ?? 'Врач') ?>
                            </span>
                        </div>
                    </div>
                    <div class="p-5 lab-content-container bg-white" ref="contentContainer" v-html="selectedTmpl.content"></div>
                </div>
            </div>

            <!-- 3. INITIAL SELECTION MODE -->
            <div class="card border-0 shadow-sm" v-if="!editingTmpl && (!selectedTmplId || !selectedPatientId)">

                <!-- No patient passed via URL -->
                <div v-if="!selectedPatient" class="card-body text-center py-5">
                    <div v-if="!selectedTmpl">
                        <div class="mb-4">
                            <div class="rounded-circle bg-warning bg-opacity-10 d-inline-flex align-items-center justify-content-center shadow-sm" style="width: 80px; height: 80px;">
                                <i class="fas fa-user-slash fa-2x text-warning opacity-75"></i>
                            </div>
                        </div>
                        <h5 class="fw-bold mb-2">Пациент не выбран</h5>
                        <p class="text-secondary small mb-4">Откройте страницу анализов из расписания приёма,<br>нажав кнопку «Анализ» рядом с пациентом.</p>
                        <a href="appointments.php" class="btn btn-primary px-4">
                            <i class="fas fa-calendar-alt me-2"></i>Перейти к расписанию
                        </a>
                    </div>
                    <!-- Template Preview when template selected from sidebar -->
                    <div v-else class="text-start">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h6 class="fw-bold mb-0"><i class="fas fa-eye me-2 text-info"></i>{{ selectedTmpl.title }}</h6>
                            <button class="btn btn-sm btn-outline-secondary" @click="selectedTmplId = ''"><i class="fas fa-times"></i></button>
                        </div>
                        <div class="border rounded-3 bg-white p-4" style="max-height: 500px; overflow-y: auto;" v-html="selectedTmpl.content"></div>
                    </div>
                </div>

                <!-- Patient loaded from URL -->
                <div v-else>
                    <!-- Patient Card -->
                    <div class="px-4 pt-4 pb-3 border-bottom d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:52px;height:52px;">
                            <i class="fas fa-user fa-lg"></i>
                        </div>
                        <div>
                            <div class="fw-bold">{{ selectedPatient.full_name }}</div>
                            <div class="small text-secondary">{{ selectedPatient.phone }}</div>
                        </div>
                        <span class="badge bg-success bg-opacity-10 text-success ms-auto px-3 py-2 rounded-pill">
                            <i class="fas fa-check-circle me-1"></i>Пациент подтверждён
                        </span>
                    </div>

                    <!-- Previous Results History -->
                    <div v-if="filteredResults.length > 0" class="px-4 pt-3">
                        <h6 class="fw-bold text-secondary small mb-2"><i class="fas fa-history me-1"></i>Предыдущие результаты ({{ filteredResults.length }})</h6>
                        <div class="table-responsive mb-0">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th style="font-size:12px;">Дата</th>
                                        <th style="font-size:12px;">Шаблон</th>
                                        <th style="font-size:12px;">Врач</th>
                                        <th class="text-end" style="font-size:12px;">Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="r in filteredResults" :key="r.id">
                                        <td class="small">{{ formatDate(r.created_at) }}</td>
                                        <td class="small fw-medium">{{ r.template_title }}</td>
                                        <td class="small text-secondary">{{ r.doctor_name || '—' }}</td>
                                        <td class="text-end">
                                            <a :href="'lab_print.php?patient_id=' + selectedPatient.id + '&template_id=' + r.template_id + '&result_id=' + r.id"
                                               target="_blank" class="btn btn-sm btn-light text-primary" title="Просмотр">
                                                <i class="fas fa-print"></i>
                                            </a>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Template Select -->
                    <div class="p-4">
                        <div v-if="!canFillAnalysis" class="alert alert-warning d-flex align-items-center mb-3" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <div class="small">Приём от <strong>{{ appointmentDate }}</strong> — заполнение анализа недоступно (только за сегодня).</div>
                        </div>
                        <label class="form-label small text-secondary fw-bold mb-2">Выберите бланк обследования</label>
                        <select v-model="selectedTmplId" class="form-select form-select-lg border-0 bg-light shadow-none rounded-3 mb-4">
                            <option value="">-- Выберите из списка --</option>
                            <optgroup v-for="cat in templatesByCategory" :key="cat.name" :label="cat.name">
                                <option v-for="t in cat.templates" :key="t.id" :value="t.id">{{ t.title }}</option>
                            </optgroup>
                        </select>
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary btn-lg py-3 shadow fw-bold rounded-3"
                                    @click="saveAndPrint" :disabled="!selectedTmplId">
                                <i class="fas fa-edit me-2"></i>Заполнить и напечатать
                            </button>
                            <button class="btn btn-link text-secondary btn-sm"
                                    @click="printBlank" :disabled="!selectedTmplId">
                                Печать пустого бланка
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
    const { createApp, ref, onMounted, watch, nextTick } = Vue;

    createApp({
        setup() {
            const templates = ref([]);
            const patients = ref([]);
            const doctors = ref([]);
            const specialties = ref([]);
            const previousResults = ref([]);
            const showOnlyMyResults = ref(false);
            const editingTmpl = ref(null);
            
            const selectedPatientId = ref('');
            const selectedPatient = ref(null);
            const selectedTmplId = ref('');
            const selectedDoctorId = ref('<?= $_SESSION['doctor_id'] ?? '' ?>');
            const currentDoctorId = '<?= $_SESSION['doctor_id'] ?? '' ?>';
            
            const activeCategory = ref('all');
            const templateSearch = ref('');
            const patientSearch = ref('');
            const showPatientList = ref(false);
            const appointmentDate = ref('');

            // Specialty locked for current doctor (empty string = admin, sees all)
            const doctorSpecialty = '<?= addslashes($doctorSpecialty) ?>';
            const currentRole = '<?= $_SESSION['role'] ?>';

            const contentContainer = ref(null);

            const canFillAnalysis = Vue.computed(() => {
                if (currentRole === 'admin' || currentRole === 'cashier') return true;
                if (!appointmentDate.value) return true;
                const today = new Date().toISOString().split('T')[0];
                return appointmentDate.value === today;
            });

            watch(selectedTmplId, async (newVal) => {
                const tmpl = templates.value.find(t => t.id == newVal);
                if (tmpl && ([2, 3, 5, 6, 9, 11].includes(Number(tmpl.id)) || tmpl.title.toUpperCase().includes('БИОХИМИЧЕСКИЕ') || tmpl.title.toUpperCase().includes('BIOKIM'))) {
                    await nextTick();
                    injectCheckboxes();
                }
            });

            const injectCheckboxes = () => {
                const container = contentContainer.value;
                if (!container) return;
                const table = container.querySelector('table');
                if (!table) return;
                if (table.querySelector('.row-checkbox')) return;

                const rows = table.querySelectorAll('tr');
                rows.forEach((row, index) => {
                    const hasInput = row.querySelector('input');
                    const isHeader = row.querySelector('th');
                    if (isHeader) {
                        const th = document.createElement('th');
                        th.innerHTML = '<input type="checkbox" checked onclick="const cbs=this.closest(\'table\').querySelectorAll(\'.row-checkbox\'); cbs.forEach(cb=>cb.checked=this.checked)">';
                        th.style.width = '30px';
                        row.insertBefore(th, row.firstChild);
                    } else if (hasInput) {
                        const td = document.createElement('td');
                        td.innerHTML = '<input type="checkbox" checked class="row-checkbox">';
                        td.style.textAlign = 'center';
                        row.insertBefore(td, row.firstChild);
                    } else if (index > 0) {
                        const td = document.createElement('td');
                        row.insertBefore(td, row.firstChild);
                    }
                });
            };

            // --- Doctor computed ---
            const selectedDoctor = Vue.computed(() => {
                return doctors.value.find(d => d.id == selectedDoctorId.value) || null;
            });

            // When doctor changes -> auto-set activeCategory to doctor's specialty
            const onDoctorChange = () => {
                selectedTmplId.value = '';
                const doc = selectedDoctor.value;
                if (doc && doc.specialty_name) {
                    activeCategory.value = doc.specialty_name.toLowerCase();
                } else {
                    activeCategory.value = 'all';
                }
            };

            const clearDoctorFilter = () => {
                activeCategory.value = 'all';
            };

            // Templates filtered by activeCategory (matching specialty name)
            const filteredTemplates = Vue.computed(() => {
                let list = templates.value;
                if (activeCategory.value !== 'all') {
                    list = list.filter(t => t.category === activeCategory.value);
                }
                if (templateSearch.value) {
                    const q = templateSearch.value.toLowerCase();
                    list = list.filter(t => t.title.toLowerCase().includes(q));
                }
                return list;
            });

            // Templates grouped by category for <optgroup> display
            const templatesByCategory = Vue.computed(() => {
                const list = filteredTemplates.value;
                const groups = {};
                list.forEach(t => {
                    const cat = t.category || 'Прочее';
                    if (!groups[cat]) groups[cat] = { name: cat, templates: [] };
                    groups[cat].templates.push(t);
                });
                return Object.values(groups);
            });

            const uniqueCategories = Vue.computed(() => {
                const cats = templates.value.map(t => t.category);
                return [...new Set(cats)].filter(c => c);
            });

            const filteredResults = Vue.computed(() => {
                if (!showOnlyMyResults.value) return previousResults.value;
                return previousResults.value.filter(r => r.doctor_id == currentDoctorId);
            });

            const selectedTmpl = Vue.computed(() => {
                return templates.value.find(t => t.id == selectedTmplId.value);
            });

            const categoryBadgeClass = (cat) => {
                const maps = {
                    'laboratory': 'bg-info text-info',
                    'uzi': 'bg-success text-success',
                    'mrt': 'bg-danger text-danger'
                };
                return maps[cat] || 'bg-primary text-primary';
            };

            const selectTmpl = (tmpl) => {
                selectedTmplId.value = tmpl.id;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            };

            const filteredPatients = Vue.computed(() => {
                if (!patientSearch.value) return patients.value.slice(0, 10);
                const q = patientSearch.value.toLowerCase();
                return patients.value.filter(p => 
                    p.full_name.toLowerCase().includes(q) || 
                    p.phone.includes(q)
                ).slice(0, 10);
            });

            const fetchPreviousResults = async (patientId) => {
                const res = await axios.get(`../api/lab_results.php?patient_id=${patientId}`);
                const payload = res.data.data || res.data;
                previousResults.value = payload.data || payload || [];
            };

            const selectPatient = (p) => {
                selectedPatient.value = p;
                selectedPatientId.value = p.id;
                patientSearch.value = p.full_name;
                showPatientList.value = false;
                fetchPreviousResults(p.id);
            };

            const newTemplate = () => {
                editingTmpl.value = { title: '', content: '', category: '' };
            };

            const editTemplate = (tmpl) => {
                editingTmpl.value = { ...tmpl };
            };

            const saveTemplate = async () => {
                try {
                    await axios.post('../api/lab_templates.php', editingTmpl.value);
                    editingTmpl.value = null;
                    showToast('Шаблон сохранён', 'success');
                    fetchData();
                } catch (e) {
                    showToast('Ошибка сохранения: ' + (e.response?.data?.error || e.message), 'danger');
                }
            };

            const cloneTemplate = async (tmpl) => {
                try {
                    const clone = {
                        title: tmpl.title + ' (копия)',
                        content: tmpl.content,
                        category: tmpl.category
                    };
                    await axios.post('../api/lab_templates.php', clone);
                    showToast('Шаблон дублирован', 'success');
                    fetchData();
                } catch (e) {
                    showToast('Ошибка дублирования: ' + (e.response?.data?.error || e.message), 'danger');
                }
            };

            const deleteTemplate = async (id) => {
                confirmDelete('Удалить шаблон?', 'Шаблон будет удалён безвозвратно.', async () => {
                    try {
                        const res = await axios.delete(`../api/lab_templates.php?id=${id}`);
                        if (res.data.success) {
                            showToast('Шаблон удалён', 'success');
                            fetchData();
                        }
                    } catch (e) {
                        const msg = e.response?.data?.error || 'Нельзя удалить шаблон, так как он используется в результатах пациентов.';
                        showToast('Ошибка при удалении: ' + msg, 'danger');
                    }
                });
            };

            const fetchData = async () => {
                const settled = await Promise.allSettled([
                    axios.get('../api/lab_templates.php'),
                    axios.get('../api/patients.php?limit=100'),
                    axios.get('../api/doctors.php'),
                    axios.get('../api/specialties.php')
                ]);
                if (settled[0].status === 'fulfilled') {
                    templates.value = settled[0].value.data.data || settled[0].value.data || [];
                }
                if (settled[1].status === 'fulfilled') {
                    const pData = settled[1].value.data.data || settled[1].value.data;
                    patients.value = pData.data || pData || [];
                }
                if (settled[2].status === 'fulfilled') {
                    doctors.value = settled[2].value.data.data || settled[2].value.data || [];
                }
                if (settled[3].status === 'fulfilled') {
                    specialties.value = settled[3].value.data.data || settled[3].value.data || [];
                }

                // If doctor: lock category to their specialty immediately
                if (doctorSpecialty) {
                    activeCategory.value = doctorSpecialty;
                } else if (selectedDoctorId.value) {
                    // Admin with session doctor selected
                    const doc = doctors.value.find(d => d.id == selectedDoctorId.value);
                    if (doc && doc.specialty_name) {
                        activeCategory.value = doc.specialty_name;
                    }
                }

                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.has('template_id')) selectedTmplId.value = urlParams.get('template_id');
                if (urlParams.has('appointment_date')) appointmentDate.value = urlParams.get('appointment_date');
                if (urlParams.has('patient_id')) {
                    const pId = urlParams.get('patient_id');
                    let p = patients.value.find(x => x.id == pId);
                    if (!p) {
                        try {
                            const pRes = await axios.get(`../api/patients.php?id=${pId}`);
                            const pData = pRes.data.data || pRes.data;
                            if (pData && pData.id) {
                                patients.value.push(pData);
                                p = pData;
                            }
                        } catch (e) {
                            console.error("Error fetching patient by ID:", e);
                        }
                    }
                    if (p) selectPatient(p);
                }
            };

            const saveAndPrint = async () => {
                try {
                    const container = contentContainer.value;
                    const clone = container.cloneNode(true);
                    
                    const tmpl = selectedTmpl.value;
                    if (tmpl && ([2, 3, 5, 6, 9, 11].includes(Number(tmpl.id)) || tmpl.title.toUpperCase().includes('БИОХИМИЧЕСКИЕ') || tmpl.title.toUpperCase().includes('BIOKIM'))) {
                        const originalRows = container.querySelectorAll('tr');
                        const cloneRows = clone.querySelectorAll('tr');
                        originalRows.forEach((origRow, i) => {
                            const cb = origRow.querySelector('.row-checkbox');
                            if (cb && !cb.checked) {
                                if (cloneRows[i]) cloneRows[i].remove();
                            }
                        });
                        clone.querySelectorAll('tr').forEach(row => {
                            if (row.firstChild && (row.firstChild.tagName === 'TD' || row.firstChild.tagName === 'TH')) {
                                row.removeChild(row.firstChild);
                            }
                        });
                    }

                    const inputs = clone.querySelectorAll('input, textarea');
                    
                    inputs.forEach(input => {
                        const span = document.createElement('span');
                        span.textContent = input.value;
                        span.style.fontWeight = 'bold';
                        span.style.textDecoration = 'underline';
                        input.parentNode.replaceChild(span, input);
                    });

                    const finalHTML = clone.innerHTML;

                    const res = await axios.post('../api/lab_results.php', {
                        patient_id: selectedPatientId.value,
                        template_id: selectedTmplId.value,
                        doctor_id: selectedDoctorId.value,
                        result_data: finalHTML
                    });

                    if (res.data.success) {
                        window.open(`lab_print.php?patient_id=${selectedPatientId.value}&template_id=${selectedTmplId.value}&result_id=${res.data.data?.id || res.data.id}`, '_blank');
                        fetchPreviousResults(selectedPatientId.value);
                        showToast('Результат сохранён', 'success');
                    }
                } catch (e) {
                    showToast('Ошибка сохранения: ' + (e.response?.data?.error || e.message), 'danger');
                }
            };

            const printBlank = () => {
                window.open(`lab_print.php?patient_id=${selectedPatientId.value}&template_id=${selectedTmplId.value}`, '_blank');
            };

            const formatDate = (d) => {
                if (!d) return '—';
                const date = new Date(d);
                return isNaN(date.getTime()) ? '—' : date.toLocaleDateString('ru-RU');
            };

            onMounted(() => {
                fetchData();
                window.addEventListener('click', (e) => {
                    if (!e.target.closest('.position-relative')) {
                        showPatientList.value = false;
                    }
                });
            });

            return { 
                templates, patients, doctors, specialties, previousResults, showOnlyMyResults,
                editingTmpl, selectedPatientId, selectedTmplId, selectedDoctorId,
                selectedDoctor, onDoctorChange, clearDoctorFilter,
                patientSearch, showPatientList, filteredPatients, filteredResults, selectedPatient,
                uniqueCategories, activeCategory, templateSearch, filteredTemplates, templatesByCategory, specialties,
                newTemplate, editTemplate, cloneTemplate, saveTemplate, deleteTemplate,
                printBlank, saveAndPrint, selectPatient, formatDate,
                contentContainer, selectedTmpl, categoryBadgeClass, selectTmpl,
                appointmentDate, canFillAnalysis
            };
        }
    }).mount('#app');
</script>

<?php include '../includes/footer.php'; ?>