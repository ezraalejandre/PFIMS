import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../widgets/app_bottom_nav_bar.dart';
import '../widgets/app_header.dart';
import '../services/projects_service.dart';

const Color kDarkPill = Color(0xFF14161F);
const Color kAtRisk = Color(0xFFE08A2C);
const Color kOnTrack = Color(0xFF1F9254);
const Color kDelayedRed = Color(0xFFE5483B);
const Color kPending = Color(0xFF64748B);

const Color _structureBg = Color(0xFFFBE3F2);
const Color _structureText = Color(0xFFC0388F);
const Color _finishingBg = Color(0xFFE1F6E8);
const Color _finishingText = kOnTrack;
const Color _completeBg = Color(0xFFEDEDED);
const Color _completeText = Color(0xFF6B7280);

const Color _foundationBg = Color(0xFFFBEEDD);
const Color _foundationText = Color(0xFFC97A2B);
const Color _planningBg = Color(0xFFE3EEFB);
const Color _planningText = Color(0xFF2B63C9);

const List<String> kPhases = ['Planning', 'Foundation', 'Structure', 'Finishing', 'Complete'];
const List<String> kStatuses = ['Pending', 'On Track', 'At Risk', 'Delayed', 'Completed'];

const List<String> _monthAbbr = [
  'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
  'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
];

String _formatDate(DateTime? date) {
  if (date == null) return '—';
  return '${_monthAbbr[date.month - 1]} ${date.day}, ${date.year}';
}

DateTime? _parseDate(dynamic value) {
  if (value == null) return null;
  final str = value.toString();
  if (str.isEmpty) return null;
  return DateTime.tryParse(str);
}

Color _tagBgForPhase(String phase) {
  switch (phase.trim().toLowerCase()) {
    case 'planning':
      return _planningBg;
    case 'foundation':
      return _foundationBg;
    case 'structure':
      return _structureBg;
    case 'finishing':
      return _finishingBg;
    case 'complete':
    case 'completed':
      return _completeBg;
    default:
      return _completeBg;
  }
}

Color _tagTextForPhase(String phase) {
  switch (phase.trim().toLowerCase()) {
    case 'planning':
      return _planningText;
    case 'foundation':
      return _foundationText;
    case 'structure':
      return _structureText;
    case 'finishing':
      return _finishingText;
    case 'complete':
    case 'completed':
      return _completeText;
    default:
      return _completeText;
  }
}

Color _colorForStatus(String status) {
  switch (status.trim().toLowerCase()) {
    case 'pending':
      return kPending;
    case 'on track':
      return kOnTrack;
    case 'at risk':
      return kAtRisk;
    case 'delayed':
      return kDelayedRed;
    case 'completed':
      return _completeText;
    default:
      return kPending;
  }
}

/// ---------------------------------------------------------------------
/// Shared blocking validation / error dialog.
///
/// Used whenever a save/update/delete cannot proceed — either because the
/// backend rejected the request (Laravel validation errors, "not found",
/// etc.) or because the request never reached the server (no internet /
/// server unreachable). Unlike a SnackBar, this requires the user to tap
/// "OK" to dismiss, so the failure can't be missed, and the calling modal
/// stays open (nothing is popped) so the user's entered data is preserved
/// and they are forced to correct the issue before they can proceed.
/// ---------------------------------------------------------------------
Future<void> showValidationDialog(
  BuildContext context, {
  String title = "Can't Save",
  required String message,
}) {
  return showDialog<void>(
    context: context,
    barrierDismissible: false,
    builder: (dialogContext) => AlertDialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      icon: const Icon(Icons.error_outline, color: kDelayedRed, size: 32),
      title: Text(title, textAlign: TextAlign.center),
      content: Text(message, textAlign: TextAlign.center),
      actionsAlignment: MainAxisAlignment.center,
      actions: [
        ElevatedButton(
          onPressed: () => Navigator.of(dialogContext).pop(),
          style: ElevatedButton.styleFrom(
            backgroundColor: kDarkPill,
            foregroundColor: Colors.white,
            padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 12),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
          ),
          child: const Text("OK"),
        ),
      ],
    ),
  );
}

/// ---------------------------------------------------------------------
/// Model built from a `project_tbl` row (GET /api/projects/list).
/// ---------------------------------------------------------------------
class _ProjectData {
  final int id;
  final String name;
  final String client;
  final String manager;
  final int workerCount;
  final String phase;
  final String status;
  final double percent; // 0-1
  final DateTime? startDate;
  final DateTime? estimatedEndDate;
  final DateTime? actualEndDate;

  const _ProjectData({
    required this.id,
    required this.name,
    required this.client,
    required this.manager,
    required this.workerCount,
    required this.phase,
    required this.status,
    required this.percent,
    required this.startDate,
    required this.estimatedEndDate,
    required this.actualEndDate,
  });

  factory _ProjectData.fromJson(Map<String, dynamic> json) {
    final rawPercent = json['completion_percentage'];
    final percentValue = rawPercent == null
        ? 0.0
        : (double.tryParse(rawPercent.toString()) ?? 0.0);

    return _ProjectData(
      id: json['project_id'] is int
          ? json['project_id'] as int
          : int.tryParse(json['project_id']?.toString() ?? '') ?? 0,
      name: (json['project_name'] ?? '').toString(),
      client: (json['client_name'] ?? '').toString(),
      manager: (json['project_manager'] ?? '').toString(),
      workerCount: json['worker_count'] is int
          ? json['worker_count'] as int
          : int.tryParse(json['worker_count']?.toString() ?? '') ?? 0,
      phase: (json['phase'] ?? 'Planning').toString(),
      status: (json['status'] ?? 'Pending').toString(),
      percent: (percentValue / 100).clamp(0.0, 1.0),
      startDate: _parseDate(json['start_date']),
      estimatedEndDate: _parseDate(json['estimated_end_date']),
      actualEndDate: _parseDate(json['actual_end_date']),
    );
  }

  Color get tagBg => _tagBgForPhase(phase);
  Color get tagText => _tagTextForPhase(phase);
  Color get statusColor => _colorForStatus(status);
  Color get progressColor => statusColor;

  String get startDateLabel => _formatDate(startDate);
  String get endDateLabel => _formatDate(estimatedEndDate);
  String get actualEndDateLabel =>
      actualEndDate == null ? 'Not yet completed' : _formatDate(actualEndDate);

  String get durationLabel {
    if (startDate == null || estimatedEndDate == null) return 'Duration: —';
    final days = estimatedEndDate!.difference(startDate!).inDays;
    if (days <= 0) return 'Duration: —';
    final months = days / 30.44;
    return 'Duration: ${months.toStringAsFixed(1)} mo';
  }
}

/// ---------------------------------------------------------------------

class ProjectTrackingScreen extends StatefulWidget {
  final String email;

  const ProjectTrackingScreen({super.key, this.email = ''});

  @override
  State<ProjectTrackingScreen> createState() => _ProjectTrackingScreenState();
}

class _ProjectTrackingScreenState extends State<ProjectTrackingScreen> {
  final TextEditingController _searchController = TextEditingController();
  late Future<List<_ProjectData>> _projectsFuture;

  @override
  void initState() {
    super.initState();
    _projectsFuture = _loadProjects();
    _searchController.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<List<_ProjectData>> _loadProjects() async {
    final raw = await ProjectsService.getProjects();
    return raw.map((e) => _ProjectData.fromJson(e)).toList();
  }

  Future<void> _refreshProjects() async {
    final future = _loadProjects();
    setState(() {
      _projectsFuture = future;
    });
    await future;
  }

  List<_ProjectData> _filter(List<_ProjectData> projects) {
    final query = _searchController.text.trim().toLowerCase();
    if (query.isEmpty) return projects;
    return projects
        .where((p) =>
            p.name.toLowerCase().contains(query) ||
            p.client.toLowerCase().contains(query))
        .toList();
  }

  Future<void> _openProjectDetails(_ProjectData project) async {
    final result = await showDialog<String>(
      context: context,
      barrierDismissible: false,
      builder: (context) => _ProjectDetailsModal(project: project),
    );

    if (!mounted) return;

    if (result == 'edit') {
      final updated = await showDialog<bool>(
        context: context,
        barrierDismissible: false,
        builder: (context) => _EditProjectModal(project: project),
      );
      if (updated == true && mounted) {
        await _refreshProjects();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Project updated successfully.')),
          );
        }
      }
    } else if (result == 'deleted') {
      await _refreshProjects();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Project deleted.')),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF2F3F5),
      appBar: AppHeader(email: widget.email),
      body: RefreshIndicator(
        onRefresh: _refreshProjects,
        child: FutureBuilder<List<_ProjectData>>(
          future: _projectsFuture,
          builder: (context, snapshot) {
            final isLoading = snapshot.connectionState == ConnectionState.waiting;
            final hasError = snapshot.hasError;
            final allProjects = snapshot.data ?? const <_ProjectData>[];
            final projects = _filter(allProjects);

           final totalProjects = allProjects.length;
          final activeProjects = allProjects
              .where((p) => p.status.toLowerCase() != 'completed')
              .length;

          final avgProgress = allProjects.isEmpty
              ? 0
              : ((allProjects.fold<double>(0, (sum, p) => sum + p.percent) /
                          allProjects.length) *
                      100)
                  .round();

          final needsAttention = allProjects.where((p) {
            final s = p.status.toLowerCase();
            return s == 'at risk' || s == 'delayed';
          }).length;

          final today = DateTime.now();
          final todayDateOnly = DateTime(today.year, today.month, today.day);
          final overdue = allProjects.where((p) {
            if (p.actualEndDate != null) return false; // already finished
            if (p.status.toLowerCase() == 'completed') return false;
            if (p.estimatedEndDate == null) return false;
            return p.estimatedEndDate!.isBefore(todayDateOnly);
          }).length;

            return ListView(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
              children: [
                // ---- Title + New Project button ----
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text(
                      "PROJECT TRACKING",
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                        letterSpacing: .2,
                        color: Colors.black87,
                      ),
                    ),
                    ElevatedButton.icon(
                      onPressed: () async {
                        final created = await showDialog<bool>(
                          context: context,
                          barrierDismissible: false,
                          builder: (context) => const _NewProjectModal(),
                        );
                        if (created == true && context.mounted) {
                          await _refreshProjects();
                          if (context.mounted) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              const SnackBar(
                                  content: Text('Project created successfully.')),
                            );
                          }
                        }
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: kDarkPill,
                        foregroundColor: Colors.white,
                        elevation: 0,
                        padding: const EdgeInsets.symmetric(
                            horizontal: 14, vertical: 10),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(24),
                        ),
                      ),
                      icon: const Icon(Icons.add, size: 16),
                      label: const Text(
                        "New Project",
                        style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),

                // ---- Stat tiles ----
                SizedBox(
                  height: 104, // match roughly what the tiles already render at
                  child: ListView(
                    scrollDirection: Axis.horizontal,
                    children: [
                      SizedBox(
                        width: 150,
                        child: _StatTile(
                          label: "ACTIVE PROJECTS",
                          value: isLoading ? "—" : "$activeProjects",
                          footer: isLoading ? "" : "$totalProjects total",
                          footerColor: Colors.grey[600]!,
                        ),
                      ),
                      const SizedBox(width: 10),
                      SizedBox(
                        width: 150,
                        child: _StatTile(
                          label: "AVG. PROGRESS",
                          value: isLoading ? "—" : "$avgProgress%",
                          footer: "Across active projects",
                          footerColor: Colors.grey[600]!,
                        ),
                      ),
                      const SizedBox(width: 10),
                      SizedBox(
                        width: 150,
                        child: _StatTile(
                          label: "NEEDS ATTENTION",
                          value: isLoading ? "—" : "$needsAttention",
                          footer: needsAttention > 0 ? "At risk or delayed" : "All clear",
                          footerColor: needsAttention > 0 ? kDelayedRed : Colors.grey[600]!,
                        ),
                      ),
                      const SizedBox(width: 10),
                      SizedBox(
                        width: 150,
                        child: _StatTile(
                          label: "OVERDUE",
                          value: isLoading ? "—" : "$overdue",
                          footer: overdue > 0 ? "Past deadline" : "On schedule",
                          footerColor: overdue > 0 ? kDelayedRed : Colors.grey[600]!,
                        ),
                      ),
                    ],
                  ),
                ),
                                // ---- Search + filter ----
                Row(
                  children: [
                    Expanded(
                      child: Container(
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(color: Colors.grey[200]!),
                        ),
                        child: TextField(
                          controller: _searchController,
                          style: const TextStyle(fontSize: 14),
                          decoration: InputDecoration(
                            hintText: "Search projects or clients...",
                            hintStyle:
                                TextStyle(color: Colors.grey[400], fontSize: 13.5),
                            prefixIcon: Icon(Icons.search,
                                color: Colors.grey[400], size: 20),
                            border: InputBorder.none,
                            contentPadding:
                                const EdgeInsets.symmetric(vertical: 14),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Container(
                      width: 48,
                      height: 48,
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: Colors.grey[200]!),
                      ),
                      child: IconButton(
                        onPressed: () {
                          // TODO: hook up filter sheet.
                        },
                        icon: Icon(Icons.tune, color: Colors.grey[700], size: 20),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 18),

                Text(
                  isLoading ? "LOADING PROJECTS..." : "${projects.length} PROJECTS",
                  style: TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w600,
                    color: Colors.grey[600],
                    letterSpacing: .2,
                  ),
                ),
                const SizedBox(height: 10),

                if (isLoading)
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 48),
                    child: Center(child: CircularProgressIndicator()),
                  )
                else if (hasError)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 32),
                    child: Column(
                      children: [
                        Text(
                          snapshot.error.toString().replaceFirst('Exception: ', ''),
                          textAlign: TextAlign.center,
                          style: TextStyle(color: Colors.grey[600]),
                        ),
                        const SizedBox(height: 12),
                        OutlinedButton(
                          onPressed: _refreshProjects,
                          child: const Text("Retry"),
                        ),
                      ],
                    ),
                  )
                else if (projects.isEmpty)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 32),
                    child: Center(
                      child: Text(
                        allProjects.isEmpty
                            ? "No projects yet. Tap \"New Project\" to add one."
                            : "No projects match your search.",
                        style: TextStyle(color: Colors.grey[600]),
                      ),
                    ),
                  )
                else
                  ...projects.map(
                    (p) => Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _ProjectCard(
                        data: p,
                        onTap: () => _openProjectDetails(p),
                      ),
                    ),
                  ),
              ],
            );
          },
        ),
      ),
      bottomNavigationBar: AppBottomNavBar(currentIndex: 1, email: widget.email),
    );
  }
}

class _StatTile extends StatelessWidget {
  final String label;
  final String value;
  final String footer;
  final Color footerColor;

  const _StatTile({
    required this.label,
    required this.value,
    required this.footer,
    required this.footerColor,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: Colors.grey[200]!),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: TextStyle(
              fontSize: 9.5,
              fontWeight: FontWeight.w600,
              letterSpacing: .2,
              color: Colors.grey[500],
            ),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
          const SizedBox(height: 6),
          Text(
            value,
            style: const TextStyle(
              fontSize: 22,
              fontWeight: FontWeight.bold,
              color: Colors.black87,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            footer,
            style: TextStyle(
                fontSize: 10.5, fontWeight: FontWeight.w600, color: footerColor),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
        ],
      ),
    );
  }
}

class _ProjectCard extends StatelessWidget {
  final _ProjectData data;
  final VoidCallback onTap;

  const _ProjectCard({
    required this.data,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(14),
      onTap: onTap,
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: .04),
              blurRadius: 10,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        data.name,
                        style: const TextStyle(
                          fontSize: 15.5,
                          fontWeight: FontWeight.bold,
                          color: Colors.black87,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        data.client,
                        style: TextStyle(fontSize: 12.5, color: Colors.grey[500]),
                      ),
                    ],
                  ),
                ),
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: data.tagBg,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    data.phase,
                    style: TextStyle(
                      fontSize: 11.5,
                      fontWeight: FontWeight.w600,
                      color: data.tagText,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: LinearProgressIndicator(
                value: data.percent,
                minHeight: 8,
                backgroundColor: Colors.grey[200],
                valueColor: AlwaysStoppedAnimation<Color>(data.progressColor),
              ),
            ),
            const SizedBox(height: 12),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      "${data.startDateLabel} → ${data.endDateLabel}",
                      style: TextStyle(fontSize: 11.5, color: Colors.grey[500]),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      data.durationLabel,
                      style: TextStyle(fontSize: 11.5, color: Colors.grey[500]),
                    ),
                  ],
                ),
                Padding(
                  padding: const EdgeInsets.only(top: 2),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Container(
                        width: 6,
                        height: 6,
                        margin: const EdgeInsets.only(right: 5),
                        decoration: BoxDecoration(
                          color: data.statusColor,
                          shape: BoxShape.circle,
                        ),
                      ),
                      Text(
                        data.status,
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                          color: data.statusColor,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _NewProjectModal extends StatefulWidget {
  const _NewProjectModal();

  @override
  State<_NewProjectModal> createState() => _NewProjectModalState();
}

class _NewProjectModalState extends State<_NewProjectModal> {
  int _currentStep = 0; // 0 = Project info, 1 = Team & Schedule, 2 = Review

  final TextEditingController _projectController = TextEditingController();
  final TextEditingController _clientController = TextEditingController();
  final TextEditingController _managerController = TextEditingController();
  final TextEditingController _workersController =
      TextEditingController(text: "0");

  DateTime? _startDate;
  DateTime? _endDate;
  bool _isSaving = false;

  @override
  void dispose() {
    _projectController.dispose();
    _clientController.dispose();
    _managerController.dispose();
    _workersController.dispose();
    super.dispose();
  }

  void _goTo(int step) => setState(() => _currentStep = step);

  bool get _step1Valid =>
      _projectController.text.trim().isNotEmpty &&
      _clientController.text.trim().isNotEmpty;

  String? get _workerCountError {
    final text = _workersController.text.trim();
    if (text.isEmpty) return "Required";
    final n = int.tryParse(text);
    if (n == null) return "Enter a whole number";
    if (n < 0) return "Must be 0 or more";
    return null;
  }

  bool get _step2Valid =>
      _managerController.text.trim().isNotEmpty &&
      _startDate != null &&
      _endDate != null &&
      _workerCountError == null;

  /// Collects every validation problem across both steps so we can show a
  /// single, complete list to the user instead of stopping at the first
  /// one. Used as a final safety net right before the Save button's
  /// request is sent — the Continue buttons already block navigation
  /// between steps when a step is invalid, but this protects against the
  /// user somehow reaching step 3 with stale/invalid data.
  List<String> get _validationErrors {
    final errors = <String>[];
    if (_projectController.text.trim().isEmpty) {
      errors.add("Project name is required.");
    }
    if (_clientController.text.trim().isEmpty) {
      errors.add("Client name is required.");
    }
    if (_managerController.text.trim().isEmpty) {
      errors.add("Project manager is required.");
    }
    final workerError = _workerCountError;
    if (workerError != null) {
      errors.add("No. of workers: $workerError.");
    }
    if (_startDate == null) {
      errors.add("Start date is required.");
    }
    if (_endDate == null) {
      errors.add("Estimated end date is required.");
    }
    if (_startDate != null &&
        _endDate != null &&
        _endDate!.isBefore(_startDate!)) {
      errors.add("Estimated end date cannot be before the start date.");
    }
    return errors;
  }

Future<void> _pickDate(bool start) async {
    final today = DateTime.now();
    final firstSelectable = DateTime(today.year, today.month, today.day);
    final current = start ? _startDate : _endDate;
    final initial = (current != null && !current.isBefore(firstSelectable))
        ? current
        : firstSelectable;

    final picked = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: firstSelectable,
      lastDate: DateTime(2035),
    );
    if (picked != null) {
      setState(() {
        if (start) {
          _startDate = picked;
        } else {
          _endDate = picked;
        }
      });
    }
  }

  Future<void> _saveProject() async {
    // Safety net: forbid the request entirely if anything is invalid, and
    // tell the user exactly what to fix via a blocking dialog rather than
    // letting a bad request go out to the server.
    final errors = _validationErrors;
    if (errors.isNotEmpty) {
      await showValidationDialog(
        context,
        title: "Check the Form",
        message: errors.join('\n'),
      );
      return;
    }

    final workerCount = int.tryParse(_workersController.text.trim());

    setState(() => _isSaving = true);

    try {
      await ProjectsService.createProject(
        projectName: _projectController.text.trim(),
        clientName: _clientController.text.trim(),
        projectManager: _managerController.text.trim(),
        startDate: _startDate!,
        estimatedEndDate: _endDate!,
        workerCount: workerCount,
      );

      if (!mounted) return;
      Navigator.of(context).pop(true); // true = a project was created
    } catch (e) {
      if (!mounted) return;
      setState(() => _isSaving = false);
      await showValidationDialog(
        context,
        message: e.toString().replaceFirst('Exception: ', ''),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.symmetric(horizontal: 28),
      child: Container(
        padding: const EdgeInsets.fromLTRB(28, 24, 28, 28),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(24),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // HEADER
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  "Add new project",
                  style: TextStyle(
                    fontSize: 30,
                    fontWeight: FontWeight.bold,
                    color: Colors.black,
                  ),
                ),
                InkWell(
                  onTap: () => Navigator.of(context).pop(),
                  child: const Icon(Icons.close, size: 26),
                ),
              ],
            ),

            const SizedBox(height: 24),

            // STEPPER
            Row(
              children: [
                _stepCircle("1", _currentStep >= 0, done: _currentStep > 0),
                _line(_currentStep > 0),
                _stepCircle("2", _currentStep >= 1, done: _currentStep > 1),
                _line(_currentStep > 1),
                _stepCircle("3", _currentStep >= 2, done: false),
              ],
            ),

            const SizedBox(height: 6),

            const Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text("Project info", style: TextStyle(color: Colors.grey, fontSize: 14)),
                Text("Team & Schedule", style: TextStyle(color: Colors.grey, fontSize: 14)),
                Text("Review", style: TextStyle(color: Colors.grey, fontSize: 14)),
              ],
            ),

            const SizedBox(height: 28),

            // BODY — all three steps are built up front and shown/hidden
            // via IndexedStack, keeping every controller alive across steps.
            IndexedStack(
              index: _currentStep,
              children: [
                _buildStep1(),
                _buildStep2(),
                _buildStep3(),
              ],
            ),

            const SizedBox(height: 32),

            // FOOTER
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                OutlinedButton(
                  onPressed: () {
                    if (_currentStep == 0) {
                      Navigator.of(context).pop();
                    } else {
                      _goTo(_currentStep - 1);
                    }
                  },
                  style: OutlinedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  child: Text(
                    _currentStep == 0 ? "Cancel" : "Back",
                    style: const TextStyle(fontSize: 16, color: Colors.black54),
                  ),
                ),
                ElevatedButton(
                  onPressed: _currentStep == 0
                      ? (_step1Valid
                          ? () => _goTo(1)
                          : () => showValidationDialog(
                                context,
                                title: "Check the Form",
                                message:
                                    "Please enter both the project name and client name before continuing.",
                              ))
                      : _currentStep == 1
                          ? (_step2Valid
                              ? () => _goTo(2)
                              : () => showValidationDialog(
                                    context,
                                    title: "Check the Form",
                                    message: _validationErrors.join('\n'),
                                  ))
                          : (_isSaving ? null : _saveProject),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: kDarkPill,
                    foregroundColor: Colors.white,
                    disabledBackgroundColor: Colors.grey[300],
                    padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  child: _currentStep < 2
                      ? const Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text("Continue", style: TextStyle(fontSize: 16)),
                            SizedBox(width: 8),
                            Icon(Icons.arrow_forward, size: 18),
                          ],
                        )
                      : _isSaving
                          ? const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                valueColor: AlwaysStoppedAnimation(Colors.white),
                              ),
                            )
                          : const Text("Save project", style: TextStyle(fontSize: 16)),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  // ---------------- Step 1: Project info ----------------
  Widget _buildStep1() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Row(
          children: [
            Icon(Icons.menu, size: 18, color: Colors.grey[400]),
            const SizedBox(width: 8),
            Text(
              "BASIC INFORMATION",
              style: TextStyle(
                color: Colors.grey[400],
                fontWeight: FontWeight.w600,
                letterSpacing: .3,
              ),
            ),
          ],
        ),
        const SizedBox(height: 18),
        _inputLabel("Project name *"),
        const SizedBox(height: 8),
        _input(controller: _projectController, hint: "e.g. Skyline Tower Phase 2"),
        const SizedBox(height: 18),
        _inputLabel("Client name *"),
        const SizedBox(height: 8),
        _input(controller: _clientController, hint: "e.g. Mega Realty Corporation"),
      ],
    );
  }

  // ---------------- Step 2: Team & Schedule ----------------
  Widget _buildStep2() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Row(
          children: [
            Icon(Icons.groups, size: 18, color: Colors.grey[400]),
            const SizedBox(width: 8),
            Text("TEAM ASSIGNMENT",
                style: TextStyle(color: Colors.grey[400], fontWeight: FontWeight.w600)),
          ],
        ),
        const SizedBox(height: 18),
        Row(
          children: [
            Expanded(
              child: _field(
                label: "Project manager *",
                child: _input(controller: _managerController, hint: "Enter project manager"),
              ),
            ),
            const SizedBox(width: 20),
            Expanded(
              child: _field(
                label: "No. of workers",
                child: _input(
                  controller: _workersController,
                  hint: "0",
                  keyboardType: TextInputType.number,
                  inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                  errorText: _workerCountError,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 28),
        Row(
          children: [
            Icon(Icons.access_time, size: 18, color: Colors.grey[400]),
            const SizedBox(width: 8),
            Text("TIMELINE",
                style: TextStyle(color: Colors.grey[400], fontWeight: FontWeight.w600)),
          ],
        ),
        const SizedBox(height: 18),
        Row(
          children: [
            Expanded(
              child: _field(
                label: "Start date *",
                child: _dateField(date: _startDate, onTap: () => _pickDate(true)),
              ),
            ),
            const SizedBox(width: 20),
            Expanded(
              child: _field(
                label: "Estimated end date *",
                child: _dateField(date: _endDate, onTap: () => _pickDate(false)),
              ),
            ),
          ],
        ),
        if (_startDate != null &&
            _endDate != null &&
            _endDate!.isBefore(_startDate!)) ...[
          const SizedBox(height: 10),
          Text(
            "Estimated end date cannot be before the start date.",
            style: TextStyle(color: kDelayedRed, fontSize: 12.5),
          ),
        ],
      ],
    );
  }

  // ---------------- Step 3: Review ----------------
  Widget _buildStep3() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(26),
      decoration: BoxDecoration(
        border: Border.all(color: Colors.grey[300]!),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          const Text("SUMMARY", style: TextStyle(fontSize: 18, color: Colors.black87)),
          const SizedBox(height: 22),
          _summaryRow("Project name",
              _projectController.text.isEmpty ? "-" : _projectController.text),
          _summaryRow("Client",
              _clientController.text.isEmpty ? "-" : _clientController.text),
          _summaryRow("Project manager",
              _managerController.text.isEmpty ? "-" : _managerController.text),
          _summaryRow("No. of workers", _workersController.text),
          _summaryRow(
            "Start date",
            _startDate == null
                ? "mm/dd/yy"
                : "${_startDate!.month}/${_startDate!.day}/${_startDate!.year}",
          ),
          _summaryRow(
            "Estimated end date",
            _endDate == null
                ? "mm/dd/yy"
                : "${_endDate!.month}/${_endDate!.day}/${_endDate!.year}",
          ),
        ],
      ),
    );
  }

  // ---------------- shared small widgets ----------------

  Widget _stepCircle(String text, bool active, {required bool done}) {
    return Container(
      width: 24,
      height: 24,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: active ? const Color(0xffff8a2b) : Colors.white,
        border: Border.all(
          color: active ? const Color(0xffff8a2b) : Colors.grey[300]!,
        ),
      ),
      child: Center(
        child: Text(
          done ? "✓" : text,
          style: TextStyle(fontSize: 12, color: active ? Colors.white : Colors.grey),
        ),
      ),
    );
  }

  Widget _line(bool active) {
    return Expanded(
      child: Container(
        height: 1,
        color: active ? const Color(0xffff8a2b) : Colors.grey[300],
      ),
    );
  }

  Widget _inputLabel(String text) {
    return Text(text, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w500));
  }

  Widget _field({required String label, required Widget child}) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: const TextStyle(fontSize: 15, color: Colors.black87)),
        const SizedBox(height: 8),
        child,
      ],
    );
  }

  Widget _input({
    required TextEditingController controller,
    required String hint,
    TextInputType? keyboardType,
    List<TextInputFormatter>? inputFormatters,
    String? errorText,
  }) {
    return TextField(
      controller: controller,
      keyboardType: keyboardType,
      inputFormatters: inputFormatters,
      decoration: InputDecoration(
        hintText: hint,
        hintStyle: TextStyle(color: Colors.grey[400]),
        errorText: errorText,
        filled: true,
        fillColor: Colors.white,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 15),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: BorderSide(color: Colors.grey[300]!),
        ),
      ),
      onChanged: (_) => setState(() {}), // keeps Continue button enable-state live
    );
  }

  Widget _dateField({required DateTime? date, required VoidCallback onTap}) {
    return InkWell(
      onTap: onTap,
      child: Container(
        height: 50,
        padding: const EdgeInsets.symmetric(horizontal: 14),
        decoration: BoxDecoration(
          border: Border.all(color: Colors.grey[300]!),
          borderRadius: BorderRadius.circular(10),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              date == null ? "mm/dd/yy" : "${date.month}/${date.day}/${date.year}",
              style: TextStyle(color: date == null ? Colors.grey[400] : Colors.black87),
            ),
            const Icon(Icons.calendar_month, size: 20),
          ],
        ),
      ),
    );
  }

  Widget _summaryRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        children: [
          SizedBox(
            width: 160,
            child: Text(label, style: TextStyle(color: Colors.grey[600], fontSize: 16)),
          ),
          Expanded(
            child: Text(value, style: TextStyle(color: Colors.grey[700], fontSize: 16)),
          ),
        ],
      ),
    );
  }
}

/// ---------------------------------------------------------------------
/// Edit project modal — pre-filled from the tapped card's data. Single
/// scrollable form (no wizard) since every field is already known and
/// just needs adjusting, including phase/status dropdowns and completion%
/// which aren't part of the New Project flow.
/// ---------------------------------------------------------------------
class _EditProjectModal extends StatefulWidget {
  final _ProjectData project;

  const _EditProjectModal({required this.project});

  @override
  State<_EditProjectModal> createState() => _EditProjectModalState();
}

class _EditProjectModalState extends State<_EditProjectModal> {
  late final TextEditingController _projectController;
  late final TextEditingController _clientController;
  late final TextEditingController _managerController;
  late final TextEditingController _workersController;
  late double _percent;

  DateTime? _startDate;
  DateTime? _endDate;
  DateTime? _actualEndDate;
  late String _phase;
  late String _status;

  bool _isSaving = false;

  @override
  void initState() {
    super.initState();
    final p = widget.project;
    _projectController = TextEditingController(text: p.name);
    _clientController = TextEditingController(text: p.client);
    _managerController = TextEditingController(text: p.manager);
    _workersController = TextEditingController(text: '${p.workerCount}');
    _percent = (p.percent * 100).clamp(0.0, 100.0);
    _startDate = p.startDate;
    _endDate = p.estimatedEndDate;
    _actualEndDate = p.actualEndDate;
    _phase = kPhases.contains(p.phase) ? p.phase : kPhases.first;
    _status = kStatuses.contains(p.status) ? p.status : kStatuses.first;
  }

  @override
  void dispose() {
    _projectController.dispose();
    _clientController.dispose();
    _managerController.dispose();
    _workersController.dispose();
    super.dispose();
  }

  String? get _workerCountError {
    final text = _workersController.text.trim();
    if (text.isEmpty) return "Required";
    final n = int.tryParse(text);
    if (n == null || n < 0) return "Enter a whole number ≥ 0";
    return null;
  }

  bool get _isValid =>
      _projectController.text.trim().isNotEmpty &&
      _clientController.text.trim().isNotEmpty &&
      _managerController.text.trim().isNotEmpty &&
      _startDate != null &&
      _endDate != null &&
      _workerCountError == null;

  /// Full list of validation problems, shown in the blocking dialog so the
  /// user knows exactly what to fix instead of the Save button silently
  /// doing nothing.
  List<String> get _validationErrors {
    final errors = <String>[];
    if (_projectController.text.trim().isEmpty) {
      errors.add("Project name is required.");
    }
    if (_clientController.text.trim().isEmpty) {
      errors.add("Client name is required.");
    }
    if (_managerController.text.trim().isEmpty) {
      errors.add("Project manager is required.");
    }
    final workerError = _workerCountError;
    if (workerError != null) {
      errors.add("No. of workers: $workerError.");
    }
    if (_startDate == null) {
      errors.add("Start date is required.");
    }
    if (_endDate == null) {
      errors.add("Estimated end date is required.");
    }
    if (_startDate != null &&
        _endDate != null &&
        _endDate!.isBefore(_startDate!)) {
      errors.add("Estimated end date cannot be before the start date.");
    }
    if (_actualEndDate != null &&
        _startDate != null &&
        _actualEndDate!.isBefore(_startDate!)) {
      errors.add("Actual end date cannot be before the start date.");
    }
    return errors;
  }

  Future<void> _pickDate({required bool isStart, required bool isActual}) async {
    final today = DateTime.now();
    final firstSelectable = DateTime(today.year, today.month, today.day);
    final current = isActual ? _actualEndDate : (isStart ? _startDate : _endDate);
    final initial = (current != null && !current.isBefore(firstSelectable))
        ? current
        : firstSelectable;

    final picked = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: firstSelectable,
      lastDate: DateTime(2035),
    );
    if (picked == null) return;
    setState(() {
      if (isActual) {
        _actualEndDate = picked;
      } else if (isStart) {
        _startDate = picked;
      } else {
        _endDate = picked;
      }
    });
  }

  Future<void> _save() async {
    // Forbid the request from going out at all if the form is invalid —
    // show every problem at once in a blocking dialog instead.
    final errors = _validationErrors;
    if (errors.isNotEmpty) {
      await showValidationDialog(
        context,
        title: "Check the Form",
        message: errors.join('\n'),
      );
      return;
    }

    setState(() => _isSaving = true);

    try {
      await ProjectsService.updateProject(
        projectId: widget.project.id,
        projectName: _projectController.text.trim(),
        clientName: _clientController.text.trim(),
        projectManager: _managerController.text.trim(),
        startDate: _startDate,
        estimatedEndDate: _endDate,
        actualEndDate: _actualEndDate,
        clearActualEndDate:
            _actualEndDate == null && widget.project.actualEndDate != null,
        workerCount: int.parse(_workersController.text.trim()),
        phase: _phase,
        status: _status,
        completionPercentage: _percent,
      );

      if (!mounted) return;
      Navigator.of(context).pop(true);
    } catch (e) {
      if (!mounted) return;
      setState(() => _isSaving = false);
      await showValidationDialog(
        context,
        message: e.toString().replaceFirst('Exception: ', ''),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.symmetric(horizontal: 24, vertical: 24),
      child: Container(
        constraints: const BoxConstraints(maxHeight: 640),
        padding: const EdgeInsets.fromLTRB(28, 24, 28, 24),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(24),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  "Edit project",
                  style: TextStyle(fontSize: 26, fontWeight: FontWeight.bold),
                ),
                InkWell(
                  onTap: () => Navigator.of(context).pop(),
                  child: const Icon(Icons.close, size: 26),
                ),
              ],
            ),
            const SizedBox(height: 20),
            Flexible(
              child: SingleChildScrollView(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _inputLabel("Project name *"),
                    const SizedBox(height: 8),
                    _input(controller: _projectController, hint: "Project name"),
                    const SizedBox(height: 16),
                    _inputLabel("Client name *"),
                    const SizedBox(height: 8),
                    _input(controller: _clientController, hint: "Client name"),
                    const SizedBox(height: 16),
                    Row(
                      children: [
                        Expanded(
                          child: _field(
                            label: "Project manager *",
                            child:
                                _input(controller: _managerController, hint: "Manager"),
                          ),
                        ),
                        const SizedBox(width: 16),
                        Expanded(
                          child: _field(
                            label: "No. of workers",
                            child: _input(
                              controller: _workersController,
                              hint: "0",
                              keyboardType: TextInputType.number,
                              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                              errorText: _workerCountError,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 16),
                    Row(
                      children: [
                        Expanded(
                          child: _field(
                            label: "Phase",
                            child: _dropdown(
                              value: _phase,
                              items: kPhases,
                              onChanged: (v) => setState(() => _phase = v!),
                            ),
                          ),
                        ),
                        const SizedBox(width: 16),
                        Expanded(
                          child: _field(
                            label: "Status",
                            child: _dropdown(
                              value: _status,
                              items: kStatuses,
                              onChanged: (v) => setState(() => _status = v!),
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 16),
                    _field(
                      label: "Completion: ${_percent.round()}%",
                      child: SliderTheme(
                        data: SliderTheme.of(context).copyWith(
                          activeTrackColor: kDarkPill,
                          thumbColor: kDarkPill,
                          overlayColor: kDarkPill.withValues(alpha: .12),
                          inactiveTrackColor: Colors.grey[200],
                        ),
                        child: Slider(
                          value: _percent,
                          min: 0,
                          max: 100,
                          divisions: 100,
                          label: "${_percent.round()}%",
                          onChanged: (v) => setState(() => _percent = v),
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),
                    Row(
                      children: [
                        Expanded(
                          child: _field(
                            label: "Start date *",
                            child: _dateField(
                              date: _startDate,
                              onTap: () => _pickDate(isStart: true, isActual: false),
                            ),
                          ),
                        ),
                        const SizedBox(width: 16),
                        Expanded(
                          child: _field(
                            label: "Estimated end date *",
                            child: _dateField(
                              date: _endDate,
                              onTap: () => _pickDate(isStart: false, isActual: false),
                            ),
                          ),
                        ),
                      ],
                    ),
                    if (_startDate != null &&
                        _endDate != null &&
                        _endDate!.isBefore(_startDate!)) ...[
                      const SizedBox(height: 8),
                      Text(
                        "Estimated end date cannot be before the start date.",
                        style: TextStyle(color: kDelayedRed, fontSize: 12.5),
                      ),
                    ],
                    const SizedBox(height: 16),
                    _field(
                      label: "Actual end date",
                      child: Row(
                        children: [
                          Expanded(
                            child: _dateField(
                              date: _actualEndDate,
                              onTap: () => _pickDate(isStart: false, isActual: true),
                            ),
                          ),
                          if (_actualEndDate != null) ...[
                            const SizedBox(width: 8),
                            IconButton(
                              tooltip: "Clear",
                              onPressed: () => setState(() => _actualEndDate = null),
                              icon: const Icon(Icons.clear, size: 20),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 20),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                OutlinedButton(
                  onPressed: () => Navigator.of(context).pop(),
                  style: OutlinedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  child: const Text("Cancel",
                      style: TextStyle(fontSize: 16, color: Colors.black54)),
                ),
                ElevatedButton(
                  onPressed: _isSaving
                      ? null
                      : (_isValid
                          ? _save
                          : () => showValidationDialog(
                                context,
                                title: "Check the Form",
                                message: _validationErrors.join('\n'),
                              )),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: kDarkPill,
                    foregroundColor: Colors.white,
                    disabledBackgroundColor: Colors.grey[300],
                    padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  child: _isSaving
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            valueColor: AlwaysStoppedAnimation(Colors.white),
                          ),
                        )
                      : const Text("Save changes", style: TextStyle(fontSize: 16)),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  // ---------------- shared small widgets ----------------

  Widget _inputLabel(String text) {
    return Text(text, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w500));
  }

  Widget _field({required String label, required Widget child}) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: const TextStyle(fontSize: 15, color: Colors.black87)),
        const SizedBox(height: 8),
        child,
      ],
    );
  }

  Widget _input({
    required TextEditingController controller,
    required String hint,
    TextInputType? keyboardType,
    List<TextInputFormatter>? inputFormatters,
    String? errorText,
  }) {
    return TextField(
      controller: controller,
      keyboardType: keyboardType,
      inputFormatters: inputFormatters,
      decoration: InputDecoration(
        hintText: hint,
        hintStyle: TextStyle(color: Colors.grey[400]),
        errorText: errorText,
        filled: true,
        fillColor: Colors.white,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 15),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: BorderSide(color: Colors.grey[300]!),
        ),
      ),
      onChanged: (_) => setState(() {}),
    );
  }

  Widget _dropdown({
    required String value,
    required List<String> items,
    required ValueChanged<String?> onChanged,
  }) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12),
      decoration: BoxDecoration(
        border: Border.all(color: Colors.grey[300]!),
        borderRadius: BorderRadius.circular(10),
      ),
      child: DropdownButtonHideUnderline(
        child: DropdownButton<String>(
          value: value,
          isExpanded: true,
          items:
              items.map((e) => DropdownMenuItem(value: e, child: Text(e))).toList(),
          onChanged: onChanged,
        ),
      ),
    );
  }

  Widget _dateField({required DateTime? date, required VoidCallback onTap}) {
    return InkWell(
      onTap: onTap,
      child: Container(
        height: 50,
        padding: const EdgeInsets.symmetric(horizontal: 14),
        decoration: BoxDecoration(
          border: Border.all(color: Colors.grey[300]!),
          borderRadius: BorderRadius.circular(10),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              date == null ? "mm/dd/yy" : "${date.month}/${date.day}/${date.year}",
              style: TextStyle(color: date == null ? Colors.grey[400] : Colors.black87),
            ),
            const Icon(Icons.calendar_month, size: 20),
          ],
        ),
      ),
    );
  }
}

/// ---------------------------------------------------------------------
/// Project details modal — shown when a card is tapped. Pops with:
///   'edit'    -> the caller should open _EditProjectModal
///   'deleted' -> the caller should refresh the list
///   null      -> nothing changed (closed / cancelled)
/// ---------------------------------------------------------------------
class _ProjectDetailsModal extends StatefulWidget {
  final _ProjectData project;

  const _ProjectDetailsModal({required this.project});

  @override
  State<_ProjectDetailsModal> createState() => _ProjectDetailsModalState();
}

class _ProjectDetailsModalState extends State<_ProjectDetailsModal> {
  bool _isDeleting = false;

  Future<void> _confirmDelete() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text("Delete project?"),
        content: Text(
          "This will permanently delete \"${widget.project.name}\". This action cannot be undone.",
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text("Cancel"),
          ),
          TextButton(
            onPressed: () => Navigator.of(context).pop(true),
            style: TextButton.styleFrom(foregroundColor: kDelayedRed),
            child: const Text("Delete"),
          ),
        ],
      ),
    );

    if (confirmed != true) return;
    if (!mounted) return;

    setState(() => _isDeleting = true);

    try {
      await ProjectsService.deleteProject(widget.project.id);
      if (!mounted) return;
      Navigator.of(context).pop('deleted');
    } catch (e) {
      if (!mounted) return;
      setState(() => _isDeleting = false);
      await showValidationDialog(
        context,
        title: "Can't Delete",
        message: e.toString().replaceFirst('Exception: ', ''),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final project = widget.project;

    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.symmetric(horizontal: 20),
      child: Container(
        padding: const EdgeInsets.fromLTRB(28, 24, 28, 28),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(24),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // HEADER
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        project.name,
                        style: const TextStyle(fontSize: 26, fontWeight: FontWeight.bold),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        project.client,
                        style: TextStyle(fontSize: 15, color: Colors.grey[600]),
                      ),
                      const SizedBox(height: 8),
                      Container(
                        padding:
                            const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                        decoration: BoxDecoration(
                          color: project.tagBg,
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Text(
                          project.phase,
                          style: TextStyle(
                            fontSize: 11.5,
                            fontWeight: FontWeight.w600,
                            color: project.tagText,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                InkWell(
                  onTap: () => Navigator.pop(context),
                  child: const Icon(Icons.close, size: 26),
                ),
              ],
            ),

            const SizedBox(height: 22),

            // PROGRESS
            Text(
              "OVERALL PROGRESS",
              style: TextStyle(fontSize: 13, color: Colors.grey[600]),
            ),
            const SizedBox(height: 8),
            ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: LinearProgressIndicator(
                value: project.percent,
                minHeight: 10,
                backgroundColor: Colors.grey[200],
                valueColor: AlwaysStoppedAnimation(project.progressColor),
              ),
            ),

            const SizedBox(height: 26),

            // DETAILS
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: Colors.grey[50],
                border: Border.all(color: Colors.grey[200]!),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Container(
                        width: 6,
                        height: 6,
                        margin: const EdgeInsets.only(right: 6),
                        decoration: BoxDecoration(
                          color: project.statusColor,
                          shape: BoxShape.circle,
                        ),
                      ),
                      Text(
                        project.status,
                        style: TextStyle(
                          fontWeight: FontWeight.w600,
                          color: project.statusColor,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  _detailRow(
                      "Project manager", project.manager.isEmpty ? "—" : project.manager),
                  _detailRow("No. of workers", "${project.workerCount}"),
                  _detailRow("Start date", project.startDateLabel),
                  _detailRow("Estimated end date", project.endDateLabel),
                  _detailRow("Actual end date", project.actualEndDateLabel),
                  _detailRow(
                      "Duration", project.durationLabel.replaceFirst("Duration: ", "")),
                ],
              ),
            ),

            const SizedBox(height: 20),

            // ACTIONS
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: _isDeleting ? null : _confirmDelete,
                    icon: _isDeleting
                        ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              valueColor: AlwaysStoppedAnimation(kDelayedRed),
                            ),
                          )
                        : const Icon(Icons.delete_outline, size: 18, color: kDelayedRed),
                    label: Text(
                      _isDeleting ? "Deleting..." : "Delete",
                      style: const TextStyle(color: kDelayedRed),
                    ),
                    style: OutlinedButton.styleFrom(
                      side: const BorderSide(color: kDelayedRed),
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: ElevatedButton.icon(
                    onPressed: _isDeleting ? null : () => Navigator.pop(context, 'edit'),
                    icon: const Icon(Icons.edit_outlined, size: 18),
                    label: const Text("Edit"),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: kDarkPill,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _detailRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        children: [
          SizedBox(
            width: 150,
            child: Text(label, style: TextStyle(color: Colors.grey[600], fontSize: 14)),
          ),
          Expanded(
            child: Text(
              value,
              style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w500),
            ),
          ),
        ],
      ),
    );
  }
}