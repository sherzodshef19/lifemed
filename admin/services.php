<?php
require_once '../config/db.php';
require_once '../includes/auth_functions.php';
check_role(['admin', 'cashier']);

$page_title = 'Услуги и прейскурант';
include '../includes/header.php';
?>

<div id="app" v-cloak>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="search-box w-50">
            <div class="input-group shadow-sm rounded-3 overflow-hidden">
                <span class="input-group-text bg-white border-end-0 px-3"><i class="fas fa-search text-muted"></i></span>
                <input type="text" v-model="searchQuery" class="form-control border-start-0 ps-0" placeholder="Поиск по названию, группе или направлению...">
            </div>
        </div>
        <div class="d-flex gap-2">
            <div class="dropdown" v-if="role === 'admin' || role === 'cashier'">
                <button class="btn btn-outline-secondary dropdown-toggle shadow-sm" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-cog me-2"></i>Управление
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                    <li><a class="dropdown-item py-2" href="#" @click.prevent="openGroupsModal"><i class="fas fa-layer-group me-2 text-primary"></i>Группы услуг</a></li>
                    <li><a class="dropdown-item py-2" href="#" @click.prevent="openDirectionsModal"><i class="fas fa-project-diagram me-2 text-success"></i>Направления</a></li>
                </ul>
            </div>
            <button v-if="role === 'admin' || role === 'cashier'" class="btn btn-primary shadow-sm px-4" @click="openModal()">
                <i class="fas fa-plus me-2"></i>Добавить услугу
            </button>
        </div>
    </div>

    <!-- Unified Table View -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light bg-opacity-50 small text-uppercase text-secondary fw-bold">
                        <tr>
                            <th class="ps-4 py-3 border-0" style="width: 50px">#</th>
                            <th class="py-3 border-0">Наименование услуги</th>
                            <th class="py-3 border-0">Категория</th>
                            <th class="py-3 border-0">Стоимость</th>
                            <th class="text-end pe-4 border-0">Действия</th>
                        </tr>
                    </thead>
                    <tbody class="border-top-0">
                        <tr v-for="(service, index) in filteredServices" :key="service.id">
                            <td class="ps-4 text-muted small">{{ index + 1 }}</td>
                            <td>
                                <div class="fw-bold text-dark">{{ service.name }}</div>
                            </td>
                            <td>
                                <div class="small">
                                    <span class="text-primary fw-medium">{{ service.group_name || 'Без группы' }}</span>
                                    <i class="fas fa-chevron-right mx-2 text-muted x-small" style="font-size: 0.7rem;"></i>
                                    <span class="text-success">{{ service.direction_name || 'Общие' }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="fw-bold text-primary">{{ formatCurrency(service.price) }}</span>
                            </td>
                            <td class="text-end pe-4">
                                <div class="btn-group btn-group-sm rounded-3 overflow-hidden border shadow-xs">
                                    <button class="btn btn-white border-0" title="Редактировать" @click="openModal(service)">
                                        <i class="fas fa-edit text-primary"></i>
                                    </button>
                                    <button v-if="role === 'admin'" class="btn btn-white border-0" title="Удалить" @click="deleteService(service.id)">
                                        <i class="fas fa-trash text-danger"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="filteredServices.length === 0">
                            <td colspan="5" class="text-center py-5">
                                <div class="opacity-25 mb-3">
                                    <i class="fas fa-search fa-3x"></i>
                                </div>
                                <h6 class="text-secondary">Услуги не найдены</h6>
                                <p class="text-muted small mb-0">Попробуйте изменить запрос или <a href="#" @click.prevent="searchQuery = ''">сбросить поиск</a></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>


    <!-- SERVICE MODAL -->
    <div class="modal fade" id="serviceModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">{{ editingId ? 'Редактировать' : 'Новая' }} услуга</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form @submit.prevent="saveService">
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Группа и направление</label>
                            <select v-model="form.direction_id" class="form-select" required>
                                <option value="">-- Выберите направление --</option>
                                <optgroup v-for="group in groups" :key="group.id" :label="group.name">
                                    <option v-for="dir in getDirectionsByGroup(group.id)" :key="dir.id" :value="dir.id">
                                        {{ dir.name }}
                                    </option>
                                </optgroup>
                            </select>
                            <div class="form-text small">Сначала создайте направления в меню управления</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Название услуги</label>
                            <input type="text" v-model="form.name" class="form-control" placeholder="Напр: УЗИ брюшной полости" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Стоимость (сум)</label>
                            <div class="input-group">
                                <input type="number" v-model="form.price" class="form-control text-primary fw-bold" placeholder="0" required>
                                <span class="input-group-text bg-light border-start-0 text-muted small">UZS</span>
                            </div>
                        </div>
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-lg shadow-sm">
                                <i class="fas fa-save me-2"></i>{{ editingId ? 'Сохранить изменения' : 'Создать услугу' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- GROUPS & DIRECTIONS MODALS (Simplified similar to Specialty in Doctors) -->
    <!-- GROUPS MODAL -->
    <div class="modal fade" id="groupsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h6 class="modal-title fw-bold"><i class="fas fa-layer-group me-2"></i>Группы услуг</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="input-group mb-4">
                        <input type="text" v-model="newGroupName" @keyup.enter="saveGroup()" class="form-control border-0 bg-light" placeholder="Название новой группы...">
                        <button class="btn btn-primary px-4" @click="saveGroup()" :disabled="!newGroupName.trim()">Добавить</button>
                    </div>
                    <div class="list-group list-group-flush border-top">
                        <div v-for="g in groups" :key="g.id" class="list-group-item d-flex align-items-center gap-3 border-0 border-bottom py-2">
                            <span class="flex-grow-1" v-if="editingGroup?.id !== g.id">{{ g.name }}</span>
                            <input v-else v-model="editingGroup.name" class="form-control form-control-sm" @keyup.enter="saveGroup(editingGroup)">
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-light" @click="editingGroup = editingGroup?.id === g.id ? null : {...g}"><i class="fas fa-edit"></i></button>
                                <button v-if="role === 'admin'" class="btn btn-light text-danger" @click="deleteGroup(g.id)"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- DIRECTIONS MODAL -->
    <div class="modal fade" id="directionsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-success text-white">
                    <h6 class="modal-title fw-bold"><i class="fas fa-project-diagram me-2"></i>Направления услуг</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-4">
                        <label class="form-label small fw-bold">Выберите группу</label>
                        <select v-model="currentGroupId" class="form-select bg-light border-0">
                            <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
                        </select>
                    </div>
                    <div class="input-group mb-4" v-if="currentGroupId">
                        <input type="text" v-model="newDirName" @keyup.enter="saveDirection()" class="form-control border-0 bg-light" placeholder="Новое направление...">
                        <button class="btn btn-success px-4" @click="saveDirection()" :disabled="!newDirName.trim()">Добавить</button>
                    </div>
                    <div class="list-group list-group-flush border-top" v-if="currentGroupId">
                        <div v-for="d in getDirectionsByGroup(currentGroupId)" :key="d.id" class="list-group-item d-flex align-items-center gap-3 border-0 border-bottom py-2">
                            <span class="flex-grow-1" v-if="editingDir?.id !== d.id">{{ d.name }}</span>
                            <input v-else v-model="editingDir.name" class="form-control form-control-sm" @keyup.enter="saveDirection(editingDir)">
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-light" @click="editingDir = editingDir?.id === d.id ? null : {...d}"><i class="fas fa-edit"></i></button>
                                <button v-if="role === 'admin'" class="btn btn-light text-danger" @click="deleteDirection(d.id)"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .shadow-xs { box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .search-box .form-control:focus { box-shadow: none; border-color: #dee2e6; }
    .table thead th { font-size: 0.75rem; letter-spacing: 0.5px; border-bottom: 1px solid #f0f0f0 !important; }
    .table tbody tr { transition: all 0.2s; }
    .table tbody tr:hover { background-color: rgba(0, 123, 255, 0.02); }
    .btn-group .btn-white:hover { background-color: #f8f9fa; }
    .dropdown-item i { width: 1.25rem; }
</style>

<script>
const { createApp, ref, computed, onMounted } = Vue;

createApp({
    setup() {
        const services = ref([]);
        const groups = ref([]);
        const directions = ref([]);
        const role = ref('<?= $_SESSION['role'] ?>');
        const editingId = ref(null);
        const form = ref({ name: '', price: '', direction_id: '' });
        const searchQuery = ref('');

        // Helper states for modals
        const newGroupName = ref('');
        const editingGroup = ref(null);
        const currentGroupId = ref(null);
        const newDirName = ref('');
        const editingDir = ref(null);

        const fetchData = async () => {
            try {
                const settled = await Promise.allSettled([
                    axios.get('../api/services.php'),
                    axios.get('../api/service_groups.php'),
                    axios.get('../api/service_directions.php')
                ]);
                if (settled[0].status === 'fulfilled') {
                    services.value = settled[0].value.data.data || settled[0].value.data || [];
                }
                if (settled[1].status === 'fulfilled') {
                    groups.value = settled[1].value.data.data || settled[1].value.data || [];
                }
                if (settled[2].status === 'fulfilled') {
                    directions.value = settled[2].value.data.data || settled[2].value.data || [];
                }
                if (groups.value.length > 0 && !currentGroupId.value) {
                    currentGroupId.value = groups.value[0].id;
                }
            } catch (e) {
                console.error(e);
                showToast('Ошибка при загрузке данных: ' + e.message, 'danger');
            }
        };

        const filteredServices = computed(() => {
            if (!searchQuery.value) return services.value;
            const q = searchQuery.value.toLowerCase();
            return services.value.filter(s => 
                s.name.toLowerCase().includes(q) ||
                (s.group_name && s.group_name.toLowerCase().includes(q)) ||
                (s.direction_name && s.direction_name.toLowerCase().includes(q))
            );
        });

        const getDirectionsByGroup = (groupId) => {
            return directions.value.filter(d => d.group_id == groupId);
        };

        const openModal = (service = null) => {
            if (service) {
                editingId.value = service.id;
                form.value = { ...service };
            } else {
                editingId.value = null;
                form.value = { name: '', price: '', direction_id: directions.value[0]?.id || '' };
            }
            bootstrap.Modal.getOrCreateInstance(document.getElementById('serviceModal')).show();
        };

        const saveService = async () => {
            try {
                const res = await axios.post('../api/services.php', { ...form.value, id: editingId.value });
                if (res.data.success) {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('serviceModal')).hide();
                    showToast('Услуга сохранена', 'success');
                    fetchData();
                } else {
                    showToast('Ошибка: ' + res.data.error, 'danger');
                }
            } catch (e) {
                showToast('Ошибка сети: ' + e.message, 'danger');
            }
        };

        const deleteService = async (id) => {
            confirmDelete('Удалить услугу?', 'Услуга будет удалена безвозвратно.', async () => {
                try {
                    const res = await axios.delete(`../api/services.php?id=${id}`);
                    if (res.data.success) {
                        showToast('Услуга удалена', 'success');
                        fetchData();
                    } else {
                        showToast('Ошибка: ' + res.data.error, 'danger');
                    }
                } catch (e) {
                    showToast('Ошибка сети: ' + e.message, 'danger');
                }
            });
        };

        const openGroupsModal = () => bootstrap.Modal.getOrCreateInstance(document.getElementById('groupsModal')).show();
        const openDirectionsModal = () => bootstrap.Modal.getOrCreateInstance(document.getElementById('directionsModal')).show();

        const saveGroup = async (group = null) => {
            if (group instanceof Event) group = null;
            
            const name = group ? group.name : newGroupName.value;
            if (!name || !name.trim()) return;
            try {
                const res = await axios.post('../api/service_groups.php', group ? { id: group.id, name } : { name });
                if (res.data.success) {
                    if (!group) newGroupName.value = '';
                    editingGroup.value = null;
                    showToast('Группа сохранена', 'success');
                    fetchData();
                } else {
                    showToast('Ошибка: ' + res.data.error, 'danger');
                }
            } catch (e) {
                showToast('Ошибка сети: ' + e.message, 'danger');
            }
        };

        const deleteGroup = async (id) => {
            confirmDelete('Удалить группу?', 'При удалении группы будут удалены все связанные направления и услуги.', async () => {
                try {
                    const res = await axios.delete(`../api/service_groups.php?id=${id}`);
                    if (res.data.success) {
                        showToast('Группа удалена', 'success');
                        fetchData();
                    } else {
                        showToast('Ошибка: ' + res.data.error, 'danger');
                    }
                } catch (e) {
                    showToast('Ошибка сети: ' + e.message, 'danger');
                }
            });
        };

        const saveDirection = async (dir = null) => {
            if (dir instanceof Event) dir = null;

            const name = dir ? dir.name : newDirName.value;
            if (!name || !name.trim()) return;
            try {
                const res = await axios.post('../api/service_directions.php', dir ? { id: dir.id, name, group_id: dir.group_id } : { name, group_id: currentGroupId.value });
                if (res.data.success) {
                    if (!dir) newDirName.value = '';
                    editingDir.value = null;
                    showToast('Направление сохранено', 'success');
                    fetchData();
                } else {
                    showToast('Ошибка: ' + res.data.error, 'danger');
                }
            } catch (e) {
                showToast('Ошибка сети: ' + e.message, 'danger');
            }
        };

        const deleteDirection = async (id) => {
            confirmDelete('Удалить направление?', 'Все услуги этого направления останутся без привязки.', async () => {
                try {
                    const res = await axios.delete(`../api/service_directions.php?id=${id}`);
                    if (res.data.success) {
                        showToast('Направление удалено', 'success');
                        fetchData();
                    } else {
                        showToast('Ошибка: ' + res.data.error, 'danger');
                    }
                } catch (e) {
                    showToast('Ошибка сети: ' + e.message, 'danger');
                }
            });
        };

        const formatCurrency = (v) => new Intl.NumberFormat('ru-RU').format(v) + ' сум';

        onMounted(fetchData);

        return {
            services, groups, directions, role, editingId, form, filteredServices, searchQuery,
            newGroupName, editingGroup, currentGroupId, newDirName, editingDir,
            getDirectionsByGroup, openModal, saveService, deleteService,
            openGroupsModal, openDirectionsModal, saveGroup, deleteGroup, saveDirection, deleteDirection,
            formatCurrency
        };
    }
}).mount('#app');
</script>

<?php include '../includes/footer.php'; ?>
