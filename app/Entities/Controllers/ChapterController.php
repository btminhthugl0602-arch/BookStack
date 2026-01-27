<?php

namespace BookStack\Entities\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use BookStack\Activity\Models\View;
use BookStack\Activity\Tools\UserEntityWatchOptions;
use BookStack\Entities\Models\Book;
use BookStack\Entities\Queries\ChapterQueries;
use BookStack\Entities\Queries\EntityQueries;
use BookStack\Entities\Repos\ChapterRepo;
use BookStack\Entities\Tools\PageContent;
use BookStack\Entities\Tools\BookContents;
use BookStack\Entities\Tools\Cloner;
use BookStack\Entities\Tools\HierarchyTransformer;
use BookStack\Entities\Tools\NextPreviousContentLocator;
use BookStack\Exceptions\NotFoundException;
use BookStack\Http\Controller;
use BookStack\Permissions\Permission;
use BookStack\References\ReferenceFetcher;
use Illuminate\Http\Request;

class ChapterController extends Controller
{
    public function __construct(
        protected ChapterRepo $chapterRepo,
        protected ChapterQueries $queries,
        protected EntityQueries $entityQueries,
        protected ReferenceFetcher $referenceFetcher,
    ) {
    }

  public function store(Request $request, string $bookSlug)
{
    
    $validated = $this->validate($request, ['name' => ['required', 'string', 'max:255']]);
    $book = $this->entityQueries->books->findVisibleBySlugOrFail($bookSlug);
    
    $chapter = $this->chapterRepo->create($validated, $book);

    // --- LOGIC PHÂN QUYỀN DUYỆT TỰ ĐỘNG ---
    $user = auth()->user();
    $currentUserId = (int)$user->id;
    $bookOwnerId = (int)$book->owned_by;

    $isAdmin = $user->hasSystemRole('admin') || $user->email === 'admin@admin.com';
    $isOwner = ($currentUserId === $bookOwnerId);
    $isViceLead = $user->roles()->where('display_name', 'Phó Lead')->exists();

    $status = ($isAdmin || $isOwner || $isViceLead) ? 'da_duyet' : 'cho_duyet';

    DB::table('duyet_bai')->updateOrInsert(
        ['entity_id' => $chapter->id, 'entity_type' => 'chapter'],
        [
            'trang_thai' => $status,
            'user_id'    => $currentUserId
           
        ]
    );

    return redirect($chapter->getUrl());
}

    // Cập nhật Chapter
    public function update(Request $request, string $bookSlug, string $chapterSlug)
{
    $validated = $this->validate($request, ['name' => ['required', 'string', 'max:255']]);
    $chapter = $this->queries->findVisibleBySlugsOrFail($bookSlug, $chapterSlug);
    $this->checkOwnablePermission(Permission::ChapterUpdate, $chapter);

    $chapter = $this->chapterRepo->update($chapter, $validated);

    $user = auth()->user();
    $isAdmin = $user->hasSystemRole('admin');
    $isOwner = ((int)$user->id === (int)$chapter->book->owned_by);
    $isViceLead = $user->roles()->where('display_name', 'Phó Lead')->exists();

    $trangThai = ($isAdmin || $isOwner || $isViceLead) ? 'da_duyet' : 'cho_duyet';

    DB::table('duyet_bai')->updateOrInsert(
        ['entity_id' => $chapter->id, 'entity_type' => 'chapter'],
        ['trang_thai' => $trangThai, 'user_id' => auth()->id()]
    );

    return redirect($chapter->getUrl());
}
    // Sao chép Chapter
    public function copy(Request $request, Cloner $cloner, string $bookSlug, string $chapterSlug)
    {
        $chapter = $this->queries->findVisibleBySlugsOrFail($bookSlug, $chapterSlug);
        $entitySelection = $request->get('entity_selection') ?: null;
        $newParentBook = $entitySelection ? $this->entityQueries->findVisibleByStringIdentifier($entitySelection) : $chapter->getParent();

        $this->checkOwnablePermission(Permission::ChapterCreate, $newParentBook);
        $chapterCopy = $cloner->cloneChapter($chapter, $newParentBook, $request->get('name') ?: $chapter->name);

        DB::table('duyet_bai')->updateOrInsert(
            ['entity_id' => $chapterCopy->id, 'entity_type' => 'chapter'],
            ['trang_thai' => 'cho_duyet', 'user_id' => auth()->id()]
        );

        return redirect($chapterCopy->getUrl());
    }

    // Hiển thị Chapter
    public function show(string $bookSlug, string $chapterSlug)
{
    $chapter = $this->queries->findVisibleBySlugsOrFail($bookSlug, $chapterSlug);
    
    // Check trạng thái duyệt
    $approval = DB::table('duyet_bai')
        ->where('entity_id', $chapter->id)
        ->where('entity_type', 'chapter')
        ->first();
    $status = $approval ? $approval->trang_thai : 'cho_duyet';

    // Xác định vai trò
    $user = auth()->user();
    $isAdmin = $user && ($user->hasSystemRole('admin') || $user->email === 'admin@admin.com');
    $isOwner = $user && ((int)$user->id === (int)$chapter->book->owned_by);
    $isViceLead = $user && $user->roles()->where('display_name', 'Phó Lead')->exists();

    $isAuthor = $user && ((int)$user->id === (int)$chapter->created_by);

    $alertHtml = "";

    if ($status === 'cho_duyet') {
        if ($isAdmin || $isOwner || $isViceLead) {
            // Nút duyệt cho Quản lý (Chống lỗi 419 với _token)
            $alertHtml = "
            <div style='background:#fff3cd; color:#856404; padding:15px; border:1px solid #ffeeba; margin-bottom:20px; border-radius:4px; display:flex; justify-content:space-between; align-items:center;'>
                <span>⚠️ <b>Thông báo:</b> Chương này đang chờ duyệt.</span>
                <form action='".url('/approve-content')."' method='POST' style='margin:0;'>
                    <input type='hidden' name='_token' value='".csrf_token()."'>
                    <input type='hidden' name='id' value='".$chapter->id."'>
                    <input type='hidden' name='type' value='chapter'>
                    <button type='submit' style='background:#28a745; color:white; border:none; padding:7px 15px; border-radius:4px; cursor:pointer; font-weight:bold;'>✅ DUYỆT CHƯƠNG</button>
                </form>
            </div>";
        } elseif ($isAuthor) {
            $alertHtml = "<div style='background:#d1ecf1; color:#0c5460; padding:15px; border:1px solid #bee5eb; margin-bottom:20px; border-radius:4px;'>ℹ️ Chương này của bạn đang chờ phê duyệt.</div>";
        } else {
            // Member khác: Hiện màn hình khóa 🔒
            return response($this->getLockScreenHtml($chapter->name), 403)->header('Content-Type', 'text/html');
        }
    }

    $sidebarTree = (new BookContents($chapter->book))->getTree();
    View::incrementFor($chapter);

    return view('chapters.show', [
        'book' => $chapter->book, 
        'chapter' => $chapter, 
        'current' => $chapter,
        'sidebarTree' => $sidebarTree, 
        'pages' => $this->entityQueries->pages->visibleForChapterList($chapter->id)->get(),
        'watchOptions' => new UserEntityWatchOptions(user(), $chapter),
        'next' => (new NextPreviousContentLocator($chapter, $sidebarTree))->getNext(),
        'previous' => (new NextPreviousContentLocator($chapter, $sidebarTree))->getPrevious(),
        'referenceCount' => $this->referenceFetcher->getReferenceCountToEntity($chapter),
        'alertHtml' => $alertHtml // Gửi thông báo ra view
    ]);
}

// Hàm bổ trợ màn hình khóa
private function getLockScreenHtml($name) {
    return "
        <div style='display: flex; justify-content: center; align-items: center; height: 100vh; font-family: sans-serif; background-color: #f7fafc;'>
            <div style='text-align: center; padding: 40px; background: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); max-width: 500px;'>
                <div style='font-size: 60px; margin-bottom: 20px;'>🔒</div>
                <h1 style='color: #e53e3e; margin-bottom: 10px; font-size: 24px;'>Chương này đang chờ phê duyệt</h1>
                <p style='color: #4a5568;'>Vui lòng quay lại sau khi nội dung đã được công khai.</p>
                <a href='".url('/')."' style='display: inline-block; margin-top: 20px; padding: 10px 20px; background-color: #448aff; color: white; text-decoration: none; border-radius: 4px;'>Quay lại trang chủ</a>
            </div>
        </div>";
}
    public function create(string $bookSlug) { $book = $this->entityQueries->books->findVisibleBySlugOrFail($bookSlug); return view('chapters.create', ['book' => $book, 'current' => $book]); }
    public function edit(string $bookSlug, string $chapterSlug) { $chapter = $this->queries->findVisibleBySlugsOrFail($bookSlug, $chapterSlug); return view('chapters.edit', ['book' => $chapter->book, 'chapter' => $chapter]); }
}
