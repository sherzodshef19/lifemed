<?php
require_once '../config/db.php';
require_once '../includes/auth_functions.php';
check_role(['admin', 'cashier']);

$page_title = 'Оформление приёма';
include '../includes/header.php';
?>

<div id="app" v-cloak>
    <!-- Global Loader Overlay -->
    <div v-if="loading" class="loader-overlay">
        <div class="loader-content">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                <span class="visually-hidden">Загрузка...</span>
            </div>
            <h5 class="mt-3 fw-bold text-dark">Загрузка...</h5>
            <p class="text-secondary small">Пожалуйста, подождите</p>
        </div>
    </div>

    <div class="container-fluid px-0">
        <div class="row g-4">
            <!-- Left Side: Categories & Services Grid -->
            <div class="col-lg-8">
                <div class="row g-3">
                    <!-- Category Sidebar -->
                <div class="col-md-4 col-xl-3">
                    <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                        <div class="card-header bg-white border-0 py-3">
                            <h6 class="fw-bold mb-0"><i class="fas fa-list-ul me-2 text-primary"></i>Категории</h6>
                        </div>
                        <div class="list-group list-group-flush border-top scroll-area" style="max-height: 70vh; overflow-y: auto;">
                            <!-- All Services -->
                            <button class="list-group-item list-group-item-action border-0 py-3 d-flex justify-content-between align-items-center"
                                    :class="{ active: !selectedGroupId }" @click="selectGroup(null)">
                                <span>Все услуги</span>
                                <span class="badge rounded-pill bg-light text-dark border">{{ services.length }}</span>
                            </button>
                            
                            <!-- Groups -->
                            <div v-for="group in groups" :key="group.id">
                                <button class="list-group-item list-group-item-action border-0 py-3 d-flex justify-content-between align-items-center"
                                        :class="{ active: selectedGroupId === group.id, 'bg-primary-subtle': selectedGroupId === group.id }"
                                        @click="selectGroup(group.id)">
                                    <span class="fw-medium">{{ group.name }}</span>
                                    <i class="fas fa-chevron-right small opacity-50" v-if="selectedGroupId !== group.id"></i>
                                    <i class="fas fa-chevron-down small" v-else></i>
                                </button>
                                
                                <!-- Directions (Subcategories) - Visible when group is selected -->
                                <div v-if="selectedGroupId === group.id" class="bg-light bg-opacity-50 border-bottom">
                                    <button v-for="dir in getDirectionsByGroup(group.id)" :key="dir.id"
                                            class="list-group-item list-group-item-action border-0 py-2 ps-4 small"
                                            :class="{ active: selectedDirectionId === dir.id }"
                                            @click="selectDirection(dir.id)">
                                        <i class="fas fa-angle-right me-2 opacity-50"></i>{{ dir.name }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Services Grid -->
                <div class="col-md-8 col-xl-9">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="search-box flex-grow-1 me-3">
                            <div class="input-group shadow-sm rounded-3 overflow-hidden">
                                <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" v-model="searchQuery" class="form-control border-start-0 ps-0" placeholder="Поиск услуги...">
                            </div>
                        </div>
                        <div class="text-secondary small fw-medium text-nowrap">Услуг: {{ filteredServices.length }}</div>
                    </div>

                    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3 scroll-area" style="max-height: 75vh; overflow-y: auto; padding: 5px;">
                        <div class="col" v-for="service in filteredServices" :key="service.id">
                            <div class="card h-100 border-0 shadow-sm service-card rounded-4" 
                                 :class="{ 'border border-primary bg-primary-subtle bg-opacity-10': isInCart(service.id) }"
                                 @click="toggleInCart(service)">
                                <div class="card-body p-3 d-flex flex-column h-100">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill x-small" v-if="!selectedGroupId">
                                            {{ service.group_name }}
                                        </span>
                                        <div v-if="isInCart(service.id)" class="text-primary animate__animated animate__zoomIn">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                    </div>
                                    <h6 class="fw-bold text-dark mb-3 flex-grow-1" style="font-size: 0.9rem; line-height: 1.4;">{{ service.name }}</h6>
                                    <div class="d-flex justify-content-between align-items-center mt-auto">
                                        <span class="text-primary fw-bold" style="font-size: 1.1rem;">{{ formatCurrency(service.price) }}</span>
                                        <div class="btn btn-sm rounded-circle p-0" 
                                             :class="isInCart(service.id) ? 'btn-primary' : 'btn-outline-primary'"
                                             style="width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas" :class="isInCart(service.id) ? 'fa-check' : 'fa-plus'"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-if="filteredServices.length === 0" class="col-12 text-center py-5 bg-white rounded-4 shadow-sm">
                            <div class="opacity-25 mb-3"><i class="fas fa-concierge-bell fa-3x"></i></div>
                            <h6 class="text-secondary">Услуги не найдены</h6>
                            <p class="text-muted small">В этой категории пока нет услуг или измените запрос поиска</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side: Cart & Patient Selection -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-lg sticky-top" style="top: 90px; border-radius: 1.25rem;">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-4 d-flex justify-content-between">
                        Детали приёма
                        <span class="badge bg-primary rounded-pill">{{ cart.length }}</span>
                    </h6>

                    <div class="table-responsive mb-4" style="max-height: 400px;">
                        <table class="table table-sm align-middle">
                            <thead class="text-secondary small">
                                <tr>
                                    <th>Услуга</th>
                                    <th class="text-center" style="width: 100px">Кол-во</th>
                                    <th>Цена</th>
                                    <th class="text-end"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in cart" :key="index">
                                    <td class="small fw-medium">{{ item.name }}</td>
                                    <td>
                                        <div class="d-flex align-items-center justify-content-center gap-1">
                                            <button class="btn btn-xs btn-outline-secondary p-0" style="width: 20px; height: 20px;" @click="updateQuantity(index, -1)">-</button>
                                            <span class="small fw-bold mx-1">{{ item.quantity }}</span>
                                            <button class="btn btn-xs btn-outline-secondary p-0" style="width: 20px; height: 20px;" @click="updateQuantity(index, 1)">+</button>
                                        </div>
                                    </td>
                                    <td class="small text-nowrap text-end">{{ formatCurrency(item.price * item.quantity) }}</td>
                                    <td class="text-end">
                                        <button class="btn btn-sm text-danger opacity-50 hover-opacity-100" @click="removeFromCart(index)">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="cart.length === 0">
                                    <td colspan="4" class="text-center py-4 text-secondary small">Корзина пуста</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="bg-light p-3 rounded-3 mb-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-secondary">Итого к оплате:</span>
                            <h4 class="fw-bold text-primary mb-0">{{ formatCurrency(totalSum) }}</h4>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small text-secondary">Выбрать пациента</label>
                        <div v-if="!selectedPatientId" class="position-relative">
                            <input type="text" v-model="patientSearchQuery" class="form-control" 
                                   placeholder="Введите имя или телефон..." @focus="showPatientList = true">
                            
                            <!-- Search Results Dropdown -->
                            <div v-if="showPatientList && filteredPatients.length > 0" 
                                 class="position-absolute w-100 shadow-lg bg-white border rounded-3 mt-1 overflow-auto" 
                                 style="max-height: 250px; z-index: 1050;">
                                <div v-for="p in filteredPatients" :key="p.id" 
                                     class="p-3 border-bottom patient-item" 
                                     @click="selectPatient(p)">
                                    <div class="fw-bold small">{{ p.full_name }}</div>
                                    <div class="text-secondary" style="font-size: 0.75rem;">{{ p.phone }} | {{ p.dob }}</div>
                                </div>
                            </div>
                        </div>

                        <div v-else class="p-3 bg-primary bg-opacity-10 text-primary rounded-3 border border-primary border-opacity-25 d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold">{{ getSelectedPatientName() }}</div>
                                <small class="opacity-75">{{ getSelectedPatientPhone() }}</small>
                            </div>
                            <button class="btn btn-sm btn-link text-primary" @click="clearPatient"><i class="fas fa-times-circle fa-lg"></i></button>
                        </div>
                        
                        <div class="mt-2 text-end">
                            <a href="#" @click.prevent="openNewPatientModal" class="small text-decoration-none fw-medium">
                                <i class="fas fa-user-plus me-1"></i> + Новый пациент
                            </a>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small text-secondary">Назначить врача (необязательно)</label>
                        <select v-model="selectedDoctorId" class="form-select shadow-none py-2">
                            <option value="">-- Без направления к конкретному врачу --</option>
                            <option v-for="d in doctors" :value="d.id">{{ d.full_name }} ({{ d.specialty_name }})</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small text-secondary">Направивший врач (врач другой клиники)</label>
                        <input type="text" v-model="referringDoctorName" class="form-control shadow-none py-2" placeholder="ФИО или код врача из другой клиники">
                    </div>

                    <div class="row g-2">
                        <div class="col-12 mb-2">
                            <button class="btn btn-primary w-100 py-3 shadow-sm fw-bold" @click="saveAppointment(true)" :disabled="!isReady">
                                <i class="fas fa-print me-2"></i> Сохранить и Печатать
                            </button>
                        </div>
                        <div class="col-6">
                            <button class="btn btn-light w-100 py-2" @click="clearCart" :disabled="cart.length === 0">Очистить</button>
                        </div>
                        <div class="col-6">
                            <button class="btn btn-outline-primary w-100 py-2" @click="saveAppointment(false)" :disabled="!isReady">Только сохранить</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>

    <!-- New Patient Modal -->
    <div class="modal fade" id="newPatientModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark">Добавить нового пациента</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <form @submit.prevent="saveNewPatient">
                        <div class="mb-3">
                            <label class="form-label small text-secondary">ФИО</label>
                            <input type="text" v-model="newPatientForm.full_name" class="form-control" required placeholder="Иванов Иван Иванович">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label small text-secondary">Дата рождения</label>
                                <input type="date" v-model="newPatientForm.dob" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small text-secondary">Телефон</label>
                                <input type="text" v-model="newPatientForm.phone" class="form-control" required placeholder="+998 90 123-45-67">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-secondary">Адрес</label>
                            <textarea v-model="newPatientForm.address" class="form-control" rows="2" placeholder="г. Ташкент, ул. Навои..."></textarea>
                        </div>
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary py-2 fw-bold" :disabled="newPatientLoading">
                                <span v-if="newPatientLoading" class="spinner-border spinner-border-sm me-2"></span>
                                Сохранить и выбрать
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .service-card {
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid rgba(0,0,0,0.05) !important;
    }
    .service-card:hover {
        border-color: var(--bs-primary) !important;
        transform: translateY(-3px);
        background-color: #f8fbff;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1) !important;
    }
    .service-card:active { transform: scale(0.98); }
    .scroll-area::-webkit-scrollbar { width: 4px; }
    .scroll-area::-webkit-scrollbar-track { background: transparent; }
    .scroll-area::-webkit-scrollbar-thumb { background: #dee2e6; border-radius: 10px; }
    .scroll-area:hover::-webkit-scrollbar-thumb { background: #ced4da; }
    .list-group-item.active { background-color: var(--bs-primary); border-color: var(--bs-primary); }
    .x-small { font-size: 0.7rem; }
    .patient-item {
        cursor: pointer;
        transition: background 0.2s;
    }
    .patient-item:hover {
        background: #f8fafc;
    }
    .btn-xs { padding: 0; font-size: 0.75rem; border-radius: 4px; }
    .hover-opacity-100:hover { opacity: 1 !important; }

    /* Loader Styles */
    .loader-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(5px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }
    .loader-content {
        text-align: center;
        background: white;
        padding: 2.5rem;
        border-radius: 1.5rem;
        shadow: 0 10px 25px rgba(0,0,0,0.1);
        border: 1px solid rgba(0,0,0,0.05);
    }
</style>

<script>
    const { createApp, ref, computed, onMounted, watch } = Vue;

    createApp({
        setup() {
            const services = ref([]);
            const groups = ref([]);
            const directions = ref([]);
            const searchQuery = ref('');
            const selectedGroupId = ref(null);
            const selectedDirectionId = ref(null);
            const cart = ref(JSON.parse(localStorage.getItem('registration_cart') || '[]'));
            const patients = ref([]);
            const doctors = ref([]);
            const selectedPatientId = ref('');
            const selectedPatient = ref(null);
            const selectedDoctorId = ref('');
            const referringDoctorName = ref('');
            const loading = ref(false);
            
            // Patient search state
            const patientSearchQuery = ref('');
            const showPatientList = ref(false);

            // New patient form state
            const newPatientLoading = ref(false);
            const newPatientForm = ref({ full_name: '', dob: '', phone: '', address: '' });

            const fetchData = async () => {
                loading.value = true;
                try {
                    const results = await Promise.allSettled([
                        axios.get('../api/services.php'),
                        axios.get('../api/doctors.php'),
                        axios.get('../api/service_groups.php'),
                        axios.get('../api/service_directions.php')
                    ]);

                    if (results[0].status === 'fulfilled') {
                        services.value = results[0].value.data.data || results[0].value.data || [];
                    }
                    if (results[1].status === 'fulfilled') {
                        doctors.value = results[1].value.data.data || results[1].value.data || [];
                    }
                    if (results[2].status === 'fulfilled') {
                        groups.value = results[2].value.data.data || results[2].value.data || [];
                    }
                    if (results[3].status === 'fulfilled') {
                        directions.value = results[3].value.data.data || results[3].value.data || [];
                    }

                    results.forEach((r, i) => {
                        if (r.status === 'rejected') console.error('API error [' + i + ']:', r.reason);
                    });

                    await searchPatients('');
                } catch (e) {
                    console.error('Ошибка при загрузке данных:', e);
                } finally {
                    loading.value = false;
                }
            };

            const searchPatients = async (v) => {
                const res = await axios.get(`../api/patients.php?q=${v}`);
                patients.value = res.data.data.data || res.data.data || [];
            };

            const selectGroup = (groupId) => {
                selectedGroupId.value = groupId;
                selectedDirectionId.value = null;
            };

            const selectDirection = (dirId) => {
                selectedDirectionId.value = dirId;
            };

            const getDirectionsByGroup = (groupId) => {
                return directions.value.filter(d => d.group_id == groupId);
            };

            const filteredServices = computed(() => {
                let res = services.value;

                if (selectedDirectionId.value) {
                    res = res.filter(s => s.direction_id == selectedDirectionId.value);
                } else if (selectedGroupId.value) {
                    res = res.filter(s => s.group_id == selectedGroupId.value);
                }

                if (searchQuery.value) {
                    const q = searchQuery.value.toLowerCase();
                    res = res.filter(s => 
                        s.name.toLowerCase().includes(q) ||
                        (s.group_name && s.group_name.toLowerCase().includes(q)) ||
                        (s.direction_name && s.direction_name.toLowerCase().includes(q))
                    );
                }
                return res;
            });

            // Watcher for search
            let timer;
            watch(patientSearchQuery, (newVal) => {
                if (selectedPatientId.value) return;
                clearTimeout(timer);
                timer = setTimeout(() => {
                    searchPatients(newVal);
                }, 300);
            });

            // Persist cart to localStorage
            watch(cart, (newVal) => {
                localStorage.setItem('registration_cart', JSON.stringify(newVal));
            }, { deep: true });

            const filteredPatients = computed(() => patients.value);

            const selectPatient = (p) => {
                selectedPatientId.value = p.id;
                selectedPatient.value = p;
                showPatientList.value = false;
            };

            const clearPatient = () => {
                selectedPatientId.value = '';
                selectedPatient.value = null;
                patientSearchQuery.value = '';
            };

            const openNewPatientModal = () => {
                newPatientForm.value = { full_name: '', dob: '', phone: '', address: '' };
                bootstrap.Modal.getOrCreateInstance(document.getElementById('newPatientModal')).show();
            };

            const saveNewPatient = async () => {
                newPatientLoading.value = true;
                try {
                    const res = await axios.post('../api/patients.php', newPatientForm.value);
                    if (res.data.success) {
                        const payload = res.data.data || {};
                        selectPatient(payload.patient || payload);
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('newPatientModal')).hide();
                        
                        if (payload.already_exists) {
                            console.log('Пациент уже существует, выбран существующий');
                        }
                    }
                } catch (e) {
                    showToast('Ошибка при создании пациента: ' + (e.response?.data?.error || e.message), 'danger');
                } finally {
                    newPatientLoading.value = false;
                }
            };

            const getSelectedPatientName = () => selectedPatient.value ? selectedPatient.value.full_name : '';
            const getSelectedPatientPhone = () => selectedPatient.value ? selectedPatient.value.phone : '';

            const isInCart = (serviceId) => {
                return cart.value.some(item => item.id == serviceId);
            };

            const toggleInCart = (service) => {
                const index = cart.value.findIndex(item => item.id == service.id);
                if (index > -1) {
                    cart.value.splice(index, 1);
                } else {
                    cart.value.push({ ...service, quantity: 1 });
                }
            };

            const updateQuantity = (index, delta) => {
                const item = cart.value[index];
                if (!item) return;
                const newQty = item.quantity + delta;
                if (newQty > 0) {
                    item.quantity = newQty;
                } else {
                    removeFromCart(index);
                }
            };

            const removeFromCart = (index) => {
                cart.value.splice(index, 1);
            };

            const clearCart = () => {
                cart.value = [];
                localStorage.removeItem('registration_cart');
            };

            const totalSum = computed(() => {
                return cart.value.reduce((sum, item) => sum + (parseFloat(item.price) * item.quantity), 0);
            });

            const isReady = computed(() => {
                return cart.value.length > 0 && selectedPatientId.value;
            });

            const saveAppointment = async (shouldPrint = false) => {
                if (!isReady.value) return;
                loading.value = true;

                const now = new Date();
                const currentDate = now.toISOString().split('T')[0];
                const currentTime = now.toTimeString().split(' ')[0];
                const receiptId = 'REC' + now.getTime(); // Create unique receipt ID

                try {
                    const promises = cart.value.map(item => {
                        return axios.post('../api/appointments.php', {
                            patient_id: selectedPatientId.value,
                            receipt_id: receiptId,
                            doctor_id: selectedDoctorId.value || null,
                            referring_doctor_name: referringDoctorName.value || null,
                            service_id: item.id,
                            quantity: item.quantity,
                            appointment_date: currentDate,
                            appointment_time: currentTime,
                            status: 'scheduled',
                            payment_status: 'paid'
                        });
                    });

                    const results = await Promise.all(promises);
                    const newIds = results.map(r => (r.data.data || r.data).id).join(',');
                    
                    localStorage.removeItem('registration_cart');

                    if (shouldPrint) {
                        try {
                            const printRes = await axios.get(`../api/print_escpos.php?receipt_id=${receiptId}`);
                            if (!printRes.data.success) {
                                showToast('Принтер недоступен. Открываю чек в браузере...', 'warning');
                                window.open(`receipt.php?receipt_id=${receiptId}`, '_blank');
                            } else {
                                showToast('Записи сохранены и чек отправлен на печать', 'success');
                            }
                        } catch (e) {
                            showToast('Принтер недоступен. Открываю чек в браузере...', 'warning');
                            window.open(`receipt.php?receipt_id=${receiptId}`, '_blank');
                        }
                    } else {
                        showToast('Записи успешно сохранены!', 'success');
                    }
                    
                    window.location.href = 'index.php';
                } catch (e) {
                    showToast('Ошибка при сохранении: ' + (e.response?.data?.error || e.message), 'danger');
                } finally {
                    loading.value = false;
                }
            };

            const formatCurrency = (val) => new Intl.NumberFormat('ru-RU').format(val) + ' сум';

            onMounted(fetchData);

            return { 
                services, groups, directions, filteredServices, patients, filteredPatients, doctors, 
                searchQuery, cart, selectedPatientId, selectedDoctorId, referringDoctorName, loading,
                selectedGroupId, selectedDirectionId, selectGroup, selectDirection, getDirectionsByGroup,
                patientSearchQuery, showPatientList, selectPatient, clearPatient,
                openNewPatientModal, saveNewPatient, newPatientForm, newPatientLoading,
                getSelectedPatientName, getSelectedPatientPhone,
                isInCart, toggleInCart, updateQuantity, removeFromCart, clearCart, totalSum, isReady, 
                saveAppointment, formatCurrency 
            };
        }
    }).mount('#app');
</script>

<?php include '../includes/footer.php'; ?>
