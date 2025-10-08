<?php
require_once 'config.php';
requireAuth();

$page_title = 'Categories';
$isOwner = $_SESSION['role'] === 'owner';

// Handle category save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_category' && $isOwner) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $name = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);
    
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE categories SET name=?, description=? WHERE id=?");
        $stmt->bind_param("ssi", $name, $description, $id);
        if ($stmt->execute()) {
            logActivity('CATEGORY_UPDATED', "Updated category: $name");
            setFlash('success', 'Category updated successfully');
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $description);
        if ($stmt->execute()) {
            logActivity('CATEGORY_CREATED', "Created category: $name");
            setFlash('success', 'Category added successfully');
        }
    }
    header('Location: /categories.php');
    exit;
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_category' && $isOwner) {
    $id = intval($_POST['id']);
    $conn->query("UPDATE categories SET status = 'inactive' WHERE id = $id");
    logActivity('CATEGORY_DELETED', "Deleted category ID: $id");
    setFlash('success', 'Category deleted successfully');
    header('Location: /categories.php');
    exit;
}

// Get categories with product count
$categories = [];
$result = $conn->query("SELECT c.*, COUNT(p.id) as product_count 
                        FROM categories c 
                        LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active' 
                        WHERE c.status = 'active' 
                        GROUP BY c.id 
                        ORDER BY c.name");
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

include 'header.php';
?>

<div class="mb-6 flex items-center justify-between">
    <div>
        <p class="text-gray-600">Organize your products into categories</p>
    </div>
    <?php if ($isOwner): ?>
    <button onclick="openCategoryModal()" 
            class="px-6 py-3 rounded-lg font-semibold text-white transition"
            style="background-color: <?php echo $settings['primary_color']; ?>">
        <i class="fas fa-plus mr-2"></i>Add Category
    </button>
    <?php endif; ?>
</div>

<!-- Categories Grid -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
    <?php foreach ($categories as $category): ?>
    <div class="bg-white rounded-xl p-6 shadow-sm hover:shadow-md transition">
        <div class="text-center">
            <div class="w-16 h-16 rounded-full mx-auto mb-4 flex items-center justify-center" 
                 style="background-color: <?php echo $settings['primary_color']; ?>20;">
                <i class="fas fa-tags text-2xl" style="color: <?php echo $settings['primary_color']; ?>"></i>
            </div>
            <h3 class="font-bold text-gray-800 text-lg mb-2"><?php echo htmlspecialchars($category['name']); ?></h3>
            <?php if ($category['description']): ?>
            <p class="text-sm text-gray-600 mb-3 line-clamp-2"><?php echo htmlspecialchars($category['description']); ?></p>
            <?php endif; ?>
            <p class="text-sm text-gray-500 mb-4"><?php echo $category['product_count']; ?> products</p>
            
            <div class="flex gap-2 justify-center">
                <a href="/products.php?category=<?php echo $category['id']; ?>" 
                   class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm font-medium transition">
                    View Products
                </a>
                <?php if ($isOwner): ?>
                <button onclick='editCategory(<?php echo json_encode($category); ?>)' 
                        class="px-4 py-2 bg-blue-100 text-blue-600 hover:bg-blue-200 rounded-lg text-sm font-medium transition">
                    <i class="fas fa-edit"></i>
                </button>
                <button onclick="deleteCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>')" 
                        class="px-4 py-2 bg-red-100 text-red-600 hover:bg-red-200 rounded-lg text-sm font-medium transition">
                    <i class="fas fa-trash"></i>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <!-- Add New Card -->
    <?php if ($isOwner): ?>
    <div onclick="openCategoryModal()" 
         class="bg-white rounded-xl p-6 shadow-sm hover:shadow-md transition border-2 border-dashed border-gray-300 hover:border-[<?php echo $settings['primary_color']; ?>] cursor-pointer">
        <div class="text-center h-full flex flex-col items-center justify-center">
            <div class="w-16 h-16 rounded-full bg-gray-100 mx-auto mb-4 flex items-center justify-center">
                <i class="fas fa-plus text-2xl text-gray-400"></i>
            </div>
            <p class="font-semibold text-gray-600">Add New Category</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if (empty($categories)): ?>
<div class="text-center py-12">
    <i class="fas fa-tags text-6xl text-gray-300 mb-4"></i>
    <p class="text-xl text-gray-500 mb-4">No categories yet</p>
    <?php if ($isOwner): ?>
    <button onclick="openCategoryModal()" 
            class="px-6 py-3 rounded-lg font-semibold text-white"
            style="background-color: <?php echo $settings['primary_color']; ?>">
        Create Your First Category
    </button>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Category Modal -->
<?php if ($isOwner): ?>
<div id="categoryModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-md">
        <form method="POST">
            <input type="hidden" name="action" value="save_category">
            <input type="hidden" name="id" id="categoryId">
            
            <div class="p-6 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-2xl font-bold text-gray-800" id="modalTitle">Add Category</h3>
                <button type="button" onclick="closeCategoryModal()" class="text-gray-400 hover:text-gray-600 text-2xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Category Name *</label>
                    <input type="text" name="name" id="categoryName" required 
                           class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:border-[<?php echo $settings['primary_color']; ?>] focus:outline-none">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea name="description" id="categoryDescription" rows="3" 
                              class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:border-[<?php echo $settings['primary_color']; ?>] focus:outline-none"></textarea>
                </div>
            </div>
            
            <div class="p-6 border-t border-gray-200 flex gap-3">
                <button type="button" onclick="closeCategoryModal()" 
                        class="flex-1 px-6 py-3 border-2 border-gray-300 rounded-lg font-semibold text-gray-700 hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="submit" 
                        class="flex-1 px-6 py-3 rounded-lg font-semibold text-white transition"
                        style="background-color: <?php echo $settings['primary_color']; ?>">
                    Save Category
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openCategoryModal() {
    document.getElementById('categoryModal').classList.remove('hidden');
    document.getElementById('modalTitle').textContent = 'Add Category';
    document.querySelector('#categoryModal form').reset();
    document.getElementById('categoryId').value = '';
}

function closeCategoryModal() {
    document.getElementById('categoryModal').classList.add('hidden');
}

function editCategory(category) {
    document.getElementById('categoryModal').classList.remove('hidden');
    document.getElementById('modalTitle').textContent = 'Edit Category';
    document.getElementById('categoryId').value = category.id;
    document.getElementById('categoryName').value = category.name;
    document.getElementById('categoryDescription').value = category.description || '';
}

function deleteCategory(id, name) {
    if (confirm(`Delete category "${name}"?\n\nThis will not delete products, they'll become uncategorized.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_category">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
<?php endif; ?>

<?php include 'footer.php'; ?>