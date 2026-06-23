import 'package:flutter/material.dart';
import '../widgets/ops_bottom_nav_bar.dart';
import '../widgets/app_header.dart';

const Color kDarkPill = Color(0xFF14161F);
const Color kAtRisk = Color(0xFFE08A2C);
const Color kOnTrack = Color(0xFF1F9254);
const Color kDelayedRed = Color(0xFFE5483B);

/// ---------------------------------------------------------------------
/// SAMPLE DATA — hard-coded for the UI build-out. Replace with real
/// project records once the backend is connected.
/// ---------------------------------------------------------------------
class _ProjectData {
  final String name;
  final String client;
  final String tag;
  final Color tagBg;
  final Color tagText;
  final double percent; // 0-1
  final Color progressColor;
  final String startDate;
  final String endDate;
  final String duration;
  final String? status; // null when there's nothing to flag (e.g. complete)
  final Color? statusColor;

  const _ProjectData({
    required this.name,
    required this.client,
    required this.tag,
    required this.tagBg,
    required this.tagText,
    required this.percent,
    required this.progressColor,
    required this.startDate,
    required this.endDate,
    required this.duration,
    this.status,
    this.statusColor,
  });
}

const Color _structureBg = Color(0xFFFBE3F2);
const Color _structureText = Color(0xFFC0388F);
const Color _finishingBg = Color(0xFFE1F6E8);
const Color _finishingText = kOnTrack;
const Color _completeBg = Color(0xFFEDEDED);
const Color _completeText = Color(0xFF6B7280);

const List<_ProjectData> _projects = [
  _ProjectData(
    name: "Skyline Tower",
    client: "Mega Realty Corp",
    tag: "Structure",
    tagBg: _structureBg,
    tagText: _structureText,
    percent: 0.72,
    progressColor: kAtRisk,
    startDate: "Jan 15, 2025",
    endDate: "Dec 30, 2025",
    duration: "Duration: 11.5 mo",
    status: "At Risk",
    statusColor: kAtRisk,
  ),
  _ProjectData(
    name: "Harbor Bridge Annex",
    client: "City Gov — NCR",
    tag: "Finishing",
    tagBg: _finishingBg,
    tagText: _finishingText,
    percent: 0.91,
    progressColor: kOnTrack,
    startDate: "Mar 1, 2025",
    endDate: "Aug 15, 2025",
    duration: "Duration: 5.5 mo",
    status: "On Track",
    statusColor: kOnTrack,
  ),
  _ProjectData(
    name: "Green Hills Residences",
    client: "Verde Homes Inc.",
    tag: "Complete",
    tagBg: _completeBg,
    tagText: _completeText,
    percent: 1.0,
    progressColor: kOnTrack,
    startDate: "Jan 5, 2025",
    endDate: "May 20, 2025",
    duration: "Duration: 4.5 mo",
    status: "Completed",
    statusColor: _completeText,
  ),
  _ProjectData(
    name: "Northgate Logistics Hub",
    client: "Pacific Storage Ltd.",
    tag: "Structure",
    tagBg: _structureBg,
    tagText: _structureText,
    percent: 0.34,
    progressColor: kOnTrack,
    startDate: "Feb 1, 2025",
    endDate: "Nov 30, 2025",
    duration: "Duration: 10 mo",
    status: "On Track",
    statusColor: kOnTrack,
  ),
  _ProjectData(
    name: "Lakeside Mixed-Use Development",
    client: "Horizon Properties",
    tag: "Finishing",
    tagBg: _finishingBg,
    tagText: _finishingText,
    percent: 0.88,
    progressColor: kAtRisk,
    startDate: "Apr 10, 2025",
    endDate: "Oct 5, 2025",
    duration: "Duration: 6 mo",
    status: "At Risk",
    statusColor: kAtRisk,
  ),
];

/// ---------------------------------------------------------------------

class OpsProjectTrackingScreen extends StatefulWidget {
  const OpsProjectTrackingScreen({super.key});

  @override
  State<OpsProjectTrackingScreen> createState() => _OpsProjectTrackingScreenState();
}

class _OpsProjectTrackingScreenState extends State<OpsProjectTrackingScreen> {
  final TextEditingController _searchController = TextEditingController();

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF2F3F5),
      appBar: const AppHeader(),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
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
          const SizedBox(height: 16),

          // ---- Stat tiles ----
          Row(
            children: [
              Expanded(
                child: _StatTile(
                  label: "ACTIVE PROJECTS",
                  value: "12",
                  footer: "↑ 2 this month",
                  footerColor: kOnTrack,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _StatTile(
                  label: "ON SCHEDULE",
                  value: "8",
                  footer: "67% of active",
                  footerColor: Colors.grey[600]!,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _StatTile(
                  label: "DELAYED",
                  value: "3",
                  footer: "Needs attention",
                  footerColor: kDelayedRed,
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),

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
            "${_projects.length} PROJECTS",
            style: TextStyle(
              fontSize: 12.5,
              fontWeight: FontWeight.w600,
              color: Colors.grey[600],
              letterSpacing: .2,
            ),
          ),
          const SizedBox(height: 10),

          ..._projects.map(
            (p) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: _ProjectCard(data: p),
            ),
          ),
        ],
      ),
     bottomNavigationBar: const OpsBottomNavBar(currentIndex: 1),
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
  const _ProjectCard({required this.data});

  @override
  Widget build(BuildContext context) {
    return Container(
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
                  data.tag,
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
          Row(
            children: [
              Expanded(
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: LinearProgressIndicator(
                    value: data.percent,
                    minHeight: 8,
                    backgroundColor: Colors.grey[200],
                    valueColor:
                        AlwaysStoppedAnimation<Color>(data.progressColor),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Text(
                "${(data.percent * 100).round()}%",
                style: TextStyle(fontSize: 12, color: Colors.grey[600]),
              ),
            ],
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
                    "${data.startDate} → ${data.endDate}",
                    style:
                        TextStyle(fontSize: 11.5, color: Colors.grey[500]),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    data.duration,
                    style:
                        TextStyle(fontSize: 11.5, color: Colors.grey[500]),
                  ),
                ],
              ),
              if (data.status != null)
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
                        data.status!,
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
    );
  }
}
